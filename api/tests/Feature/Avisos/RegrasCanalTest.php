<?php

use App\Modules\Avisos\Application\FalhaDefinitivaDeEnvio;
use App\Modules\Avisos\Application\PreferenciasDeAviso;
use App\Modules\Avisos\Contracts\Avisos;
use App\Modules\Avisos\Contracts\Destinatario;
use App\Modules\Avisos\Contracts\Referencia;
use App\Modules\Avisos\Domain\TipoAviso;
use App\Modules\Avisos\Infrastructure\Canais\MetaWhatsApp\MetaWhatsAppFalso;
use App\Modules\Avisos\Infrastructure\Canais\Postmark\PostmarkFalso;
use Illuminate\Http\Client\Request as PedidoHttp;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/** Regras de canal por categoria, janela diurna e remetente (OIK-ADR-0008 §9, §12, §14; RN-21; T-18). */
beforeEach(function (): void {
    $this->org = criarOrganizacao('Igreja Central');
    DB::connection('pgsql_dono')->table('organizacoes')->where('id', $this->org)->update(['email_contato' => 'secretaria@igreja.test']);
    $igreja = criarIgreja($this->org);

    $this->conta = criarContaVerificada(['email' => 'secretaria@exemplo.test', 'celular' => '+5511912340000']);
    criarAcesso($this->org, $this->conta, criarPessoa($this->org, $igreja));

    $this->pessoa = criarPessoa($this->org, $igreja, ['nome' => 'Joana Convidada']);
    criarContato($this->org, $this->pessoa, 'email', 'joana@exemplo.test');
    inserirComoDono('pessoa_contatos', [
        'organizacao_id' => $this->org, 'pessoa_id' => $this->pessoa, 'tipo' => 'celular', 'valor' => '+5511955550000', 'e_whatsapp' => true,
    ]);

    $this->email = app(PostmarkFalso::class);
    $this->whatsapp = app(MetaWhatsAppFalso::class);
});

function naHoraLocal(int $hora, int $minuto = 0, string $fuso = 'America/Sao_Paulo'): Carbon
{
    return Carbon::now($fuso)->setTime($hora, $minuto);
}

function registrarNaOrganizacao(object $teste, TipoAviso $tipo, Destinatario $destinatario, string $referencia = 'ref-1'): void
{
    noContexto($teste->org, null, fn () => app(Avisos::class)->registrar($tipo, [$destinatario], new Referencia('teste', $referencia)));
}

/** @return list<object> */
function enviosDaOrganizacao(): array
{
    return DB::connection('pgsql_dono')->table('envios')->orderBy('id')->get()->all();
}

it('envia o convite por e-mail e pelo WhatsApp da organização na hora, mesmo às 23h', function (): void {
    ligarNumeroWhatsApp($this->org);
    $this->travelTo(naHoraLocal(23));

    registrarNaOrganizacao($this, TipoAviso::ConvitePainel, Destinatario::pessoa($this->pessoa, '/convites/abc'));

    $envios = enviosDaOrganizacao();
    expect(array_column($envios, 'canal'))->toBe(['email', 'whatsapp'])
        ->and(array_unique(array_column($envios, 'estado')))->toBe(['enviado'])
        ->and($this->email->avisosEnviados[0]->destino)->toBe('joana@exemplo.test')
        ->and($this->whatsapp->avisosEnviados[0]->destino)->toBe('+5511955550000');
});

it('envia o convite só por e-mail quando a organização não tem número ativo, sem usar o da plataforma', function (): void {
    registrarNaOrganizacao($this, TipoAviso::ConvitePainel, Destinatario::pessoa($this->pessoa, '/convites/abc'));

    expect(array_column(enviosDaOrganizacao(), 'canal'))->toBe(['email'])
        ->and($this->email->avisosEnviados)->toHaveCount(1)
        ->and($this->whatsapp->avisosEnviados)->toBe([]);
});

it('envia o aviso de serviço por um único canal externo, o WhatsApp por padrão', function (): void {
    ligarNumeroWhatsApp($this->org);
    $this->travelTo(naHoraLocal(10));

    registrarNaOrganizacao($this, TipoAviso::TransferenciaSolicitada, Destinatario::conta($this->conta, destino: '/transferencias/1'));

    $envios = enviosDaOrganizacao();
    expect(array_column($envios, 'canal'))->toBe(['whatsapp'])
        ->and($envios[0]->estado)->toBe('enviado')
        ->and($this->whatsapp->avisosEnviados[0]->destino)->toBe('+5511912340000')
        ->and($this->email->avisosEnviados)->toBe([]);
});

it('envia o aviso de serviço pelo canal preferido da conta', function (): void {
    ligarNumeroWhatsApp($this->org);
    $this->travelTo(naHoraLocal(10));
    noContexto($this->org, null, fn () => app(PreferenciasDeAviso::class)->definir($this->conta, TipoAviso::TransferenciaSolicitada, 'whatsapp', false));

    registrarNaOrganizacao($this, TipoAviso::TransferenciaSolicitada, Destinatario::conta($this->conta));

    expect(array_column(enviosDaOrganizacao(), 'canal'))->toBe(['email'])
        ->and($this->email->avisosEnviados)->toHaveCount(1)
        ->and($this->whatsapp->avisosEnviados)->toBe([]);
});

it('reenvia o aviso de serviço pelo e-mail na falha definitiva do WhatsApp', function (): void {
    ligarNumeroWhatsApp($this->org);
    $this->travelTo(naHoraLocal(10));
    $this->whatsapp->falharCom(new FalhaDefinitivaDeEnvio('numero_sem_whatsapp'));

    registrarNaOrganizacao($this, TipoAviso::ExportacaoPronta, Destinatario::conta($this->conta));

    $envios = collect(enviosDaOrganizacao())->keyBy('canal');
    expect($envios->keys()->all())->toBe(['whatsapp', 'email'])
        ->and($envios['whatsapp']->estado)->toBe('falhou_definitivo')
        ->and($envios['email']->estado)->toBe('enviado')
        ->and($this->email->avisosEnviados[0]->destino)->toBe('secretaria@exemplo.test');
});

it('reenvia o aviso de serviço pelo e-mail quando o WhatsApp não confirma entrega em 30 minutos', function (): void {
    ligarNumeroWhatsApp($this->org);
    $this->travelTo(naHoraLocal(10));
    registrarNaOrganizacao($this, TipoAviso::TransferenciaDecidida, Destinatario::conta($this->conta));

    $this->travel(29)->minutes();
    Artisan::call('envios:varrer');
    expect(array_column(enviosDaOrganizacao(), 'canal'))->toBe(['whatsapp']);

    $this->travel(2)->minutes();
    Artisan::call('envios:varrer');
    Artisan::call('envios:varrer');

    $envios = collect(enviosDaOrganizacao())->keyBy('canal');
    expect($envios->keys()->all())->toBe(['whatsapp', 'email'])
        ->and($envios['whatsapp']->estado)->toBe('enviado')
        ->and($envios['email']->estado)->toBe('enviado')
        ->and($this->email->avisosEnviados)->toHaveCount(1);
});

it('não reenvia o convite pelo próximo canal: ele já sai em todos', function (): void {
    ligarNumeroWhatsApp($this->org);
    $this->whatsapp->falharCom(new FalhaDefinitivaDeEnvio('numero_sem_whatsapp'));

    registrarNaOrganizacao($this, TipoAviso::ConvitePainel, Destinatario::pessoa($this->pessoa));
    $this->travel(31)->minutes();
    Artisan::call('envios:varrer');

    expect(array_column(enviosDaOrganizacao(), 'canal'))->toBe(['email', 'whatsapp']);
});

it('agenda para as 8h o aviso de serviço registrado às 22h no fuso da organização', function (): void {
    ligarNumeroWhatsApp($this->org);
    $this->travelTo(naHoraLocal(22));

    registrarNaOrganizacao($this, TipoAviso::TransferenciaSolicitada, Destinatario::conta($this->conta));

    $envio = enviosDaOrganizacao()[0];
    $oitoHoras = naHoraLocal(8)->addDay();
    expect($envio->estado)->toBe('agendado')
        ->and(Carbon::parse($envio->agendado_para)->equalTo($oitoHoras))->toBeTrue()
        ->and($this->whatsapp->avisosEnviados)->toBe([]);

    $this->travelTo($oitoHoras->copy()->subMinute());
    Artisan::call('envios:varrer');
    expect(enviosDaOrganizacao()[0]->estado)->toBe('agendado');

    $this->travelTo($oitoHoras->copy()->addMinute());
    Artisan::call('envios:varrer');
    expect(enviosDaOrganizacao()[0]->estado)->toBe('enviado')
        ->and($this->whatsapp->avisosEnviados)->toHaveCount(1);
});

it('usa o fuso da organização na janela diurna', function (): void {
    DB::connection('pgsql_dono')->table('organizacoes')->where('id', $this->org)->update(['fuso' => 'America/Manaus']);
    ligarNumeroWhatsApp($this->org);
    // 21h30 em São Paulo são 20h30 em Manaus: ainda dentro da janela.
    $this->travelTo(naHoraLocal(21, 30));

    registrarNaOrganizacao($this, TipoAviso::TransferenciaSolicitada, Destinatario::conta($this->conta));

    expect(enviosDaOrganizacao()[0]->estado)->toBe('enviado');
});

it('assina o e-mail como "Nome da Igreja via Oikora", responde para a organização e não leva dado da ficha', function (): void {
    config([
        'oikora.avisos.provedores' => 'real',
        'services.postmark.token' => 'token-postmark',
        'services.postmark.remetente_avisos' => 'avisos@oikora.test',
    ]);
    Http::preventStrayRequests();
    Http::fake(['api.postmarkapp.com/*' => Http::response(['ErrorCode' => 0, 'MessageID' => 'pm-1'])]);

    registrarNaOrganizacao($this, TipoAviso::ConvitePainel, Destinatario::pessoa($this->pessoa, '/convites/abc'));

    Http::assertSentCount(1);
    Http::assertSent(function (PedidoHttp $pedido): bool {
        $corpo = $pedido->data();
        $textos = $corpo['HtmlBody'].$corpo['TextBody'];

        return $corpo['From'] === '"Igreja Central via Oikora" <avisos@oikora.test>'
            && $corpo['ReplyTo'] === 'secretaria@igreja.test'
            && $corpo['To'] === 'joana@exemplo.test'
            && str_contains($textos, 'Igreja Central')
            && str_contains($textos, '/convites/abc')
            && ! str_contains($textos, 'Joana')
            && ! str_contains($textos, 'joana@exemplo.test')
            && ! str_contains($textos, '5511955550000')
            && ! array_key_exists('Headers', $corpo);
    });
    expect(enviosDaOrganizacao()[0]->id_externo)->toBe('pm-1');
});

it('manda o WhatsApp do aviso pelo número da organização, nunca pelo da plataforma', function (): void {
    ligarNumeroWhatsApp($this->org);
    $this->travelTo(naHoraLocal(10));
    config([
        'oikora.avisos.provedores' => 'real',
        'services.meta_whatsapp.numero_id' => 'pn-plataforma',
        'services.meta_whatsapp.token' => 'token-plataforma',
    ]);
    Http::preventStrayRequests();
    Http::fake(['graph.facebook.com/*' => Http::response(['messages' => [['id' => 'wamid.1']]])]);

    registrarNaOrganizacao($this, TipoAviso::TransferenciaSolicitada, Destinatario::conta($this->conta, destino: '/transferencias/1'));

    $numero = 'pn-org-'.$this->org;
    Http::assertSent(fn (PedidoHttp $pedido): bool => str_contains($pedido->url(), "/{$numero}/messages")
        && $pedido->hasHeader('Authorization', 'Bearer token-org-'.$this->org)
        && $pedido['template']['name'] === 'servico_transferencia_solicitada'
        && $pedido['to'] === '5511912340000');
    Http::assertNotSent(fn (PedidoHttp $pedido): bool => str_contains($pedido->url(), 'pn-plataforma'));
});
