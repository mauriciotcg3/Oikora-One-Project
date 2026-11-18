<?php

use App\Modules\Avisos\Application\Descadastrar;
use App\Modules\Avisos\Application\LinksDeDescadastro;
use App\Modules\Avisos\Application\MensagemDeAviso;
use App\Modules\Avisos\Application\PreferenciaRecusada;
use App\Modules\Avisos\Application\PreferenciasDeAviso;
use App\Modules\Avisos\Contracts\Avisos;
use App\Modules\Avisos\Contracts\Destinatario;
use App\Modules\Avisos\Contracts\Referencia;
use App\Modules\Avisos\Domain\TipoAviso;
use App\Modules\Avisos\Infrastructure\Canais\Postmark\PostmarkAvisos;
use App\Modules\Avisos\Infrastructure\Canais\Postmark\PostmarkFalso;
use Illuminate\Http\Client\Request as PedidoHttp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/** Preferências e descadastro (OIK-ADR-0008 §9, §13; RFC 8058; T-18). */
beforeEach(function (): void {
    $this->org = criarOrganizacao('Igreja Central');
    $igreja = criarIgreja($this->org);
    $this->conta = criarContaVerificada();
    criarAcesso($this->org, $this->conta, criarPessoa($this->org, $igreja));

    $this->pessoa = criarPessoa($this->org, $igreja);
    $this->email = criarContato($this->org, $this->pessoa, 'email', 'joana@exemplo.test');
    $this->celular = inserirComoDono('pessoa_contatos', [
        'organizacao_id' => $this->org, 'pessoa_id' => $this->pessoa, 'tipo' => 'celular', 'valor' => '+5511955550000', 'e_whatsapp' => true,
    ]);
});

function definirPreferencia(object $teste, TipoAviso $tipo, string $canal, bool $ligado): void
{
    noContexto($teste->org, null, fn () => app(PreferenciasDeAviso::class)->definir($teste->conta, $tipo, $canal, $ligado));
}

function descadastradoEm(int $contatoId): ?string
{
    return DB::connection('pgsql_dono')->table('pessoa_contatos')->where('id', $contatoId)->value('descadastrado_em');
}

function linkDeDescadastro(object $teste, ?int $pessoaId = null): string
{
    return app(LinksDeDescadastro::class)->paraPessoa(uuidDe('organizacoes', $teste->org), uuidDe('pessoas', $pessoaId ?? $teste->pessoa), 'email');
}

it('não deixa desligar o convite, que é obrigatório', function (string $canal): void {
    expect(fn () => definirPreferencia($this, TipoAviso::ConvitePainel, $canal, false))
        ->toThrow(PreferenciaRecusada::class, 'tipo_obrigatorio');

    expect(DB::connection('pgsql_dono')->table('preferencias_aviso')->count())->toBe(0);
})->with(['email', 'whatsapp']);

it('deixa trocar o canal do aviso de serviço, mas não desligar o último', function (): void {
    definirPreferencia($this, TipoAviso::ExportacaoPronta, 'whatsapp', false);

    expect(fn () => definirPreferencia($this, TipoAviso::ExportacaoPronta, 'email', false))
        ->toThrow(PreferenciaRecusada::class, 'tipo_obrigatorio');

    $ligados = noContexto($this->org, null, fn () => app(PreferenciasDeAviso::class)->ligados($this->conta, TipoAviso::ExportacaoPronta));
    expect($ligados)->toBe(['email']);
});

it('recusa preferência de canal que o tipo não tem', function (): void {
    expect(fn () => definirPreferencia($this, TipoAviso::ExportacaoPronta, 'sms', true))
        ->toThrow(PreferenciaRecusada::class, 'canal_invalido');
});

it('grava descadastrado_em no contato pelo link assinado de um clique (RFC 8058)', function (): void {
    $this->post(linkDeDescadastro($this), ['List-Unsubscribe' => 'One-Click'])->assertNoContent();

    expect(descadastradoEm($this->email))->not->toBeNull()
        ->and(descadastradoEm($this->celular))->toBeNull();

    // Repetir não muda nada nem revela o estado.
    $this->post(linkDeDescadastro($this), ['List-Unsubscribe' => 'One-Click'])->assertNoContent();
});

it('recusa o link de descadastro adulterado', function (): void {
    $outra = criarPessoa($this->org, criarIgreja($this->org, 'Outra'));
    $outroEmail = criarContato($this->org, $outra, 'email', 'outra@exemplo.test');
    $adulterado = str_replace(uuidDe('pessoas', $this->pessoa), uuidDe('pessoas', $outra), linkDeDescadastro($this));

    $this->post($adulterado)->assertForbidden();

    expect(descadastradoEm($outroEmail))->toBeNull()
        ->and(descadastradoEm($this->email))->toBeNull();
});

it('descadastra o WhatsApp pela palavra SAIR', function (): void {
    $descadastrar = app(Descadastrar::class);

    expect(noContexto($this->org, null, fn () => $descadastrar->porMensagemWhatsApp('+5511955550000', 'quero sair')))->toBe(0)
        ->and(descadastradoEm($this->celular))->toBeNull();

    expect(noContexto($this->org, null, fn () => $descadastrar->porMensagemWhatsApp('(11) 95555-0000', '  sair ')))->toBe(1)
        ->and(descadastradoEm($this->celular))->not->toBeNull()
        ->and(descadastradoEm($this->email))->toBeNull();
});

it('continua entregando o convite, obrigatório, ao contato descadastrado', function (): void {
    $this->post(linkDeDescadastro($this))->assertNoContent();

    noContexto($this->org, null, fn () => app(Avisos::class)->registrar(
        TipoAviso::ConvitePainel, [Destinatario::pessoa($this->pessoa)], new Referencia('convite', 'c-1'),
    ));

    expect(app(PostmarkFalso::class)->avisosEnviados[0]->destino)->toBe('joana@exemplo.test');
});

it('põe List-Unsubscribe e List-Unsubscribe-Post no e-mail que leva link de descadastro', function (): void {
    config(['services.postmark.token' => 'token-postmark', 'services.postmark.remetente_avisos' => 'avisos@oikora.test']);
    Http::preventStrayRequests();
    Http::fake(['api.postmarkapp.com/*' => Http::response(['ErrorCode' => 0, 'MessageID' => 'pm-2'])]);
    $link = linkDeDescadastro($this);

    app(PostmarkAvisos::class)->enviarAviso(new MensagemDeAviso(
        tipo: TipoAviso::ExportacaoPronta,
        canal: 'email',
        destino: 'joana@exemplo.test',
        link: null,
        chaveDeIdempotencia: 'aviso:email',
        organizacaoId: $this->org,
        nomeOrganizacao: 'Igreja Central',
        linkDescadastro: $link,
    ));

    Http::assertSent(fn (PedidoHttp $pedido): bool => $pedido['Headers'] === [
        ['Name' => 'List-Unsubscribe', 'Value' => '<'.$link.'>'],
        ['Name' => 'List-Unsubscribe-Post', 'Value' => 'List-Unsubscribe=One-Click'],
    ] && str_contains($pedido['HtmlBody'], 'Não quero mais receber'));
});
