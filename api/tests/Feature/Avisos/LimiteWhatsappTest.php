<?php

use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Avisos\Contracts\Avisos;
use App\Modules\Avisos\Contracts\Destinatario;
use App\Modules\Avisos\Contracts\Referencia;
use App\Modules\Avisos\Domain\TipoAviso;
use App\Modules\Avisos\Infrastructure\Canais\MetaWhatsApp\MetaWhatsAppFalso;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Uso de mensageria e limite mensal de WhatsApp (OIK-ADR-0008 §16; U-04; T-20). */
beforeEach(function (): void {
    $this->org = criarOrganizacao('Igreja Central');
    $igreja = criarIgreja($this->org);

    // Quem configura a organização recebe os avisos do limite.
    $this->admin = criarContaVerificada(['email' => 'admin@exemplo.test', 'celular' => '+5511911110000']);
    $acessoAdmin = criarAcesso($this->org, $this->admin, criarPessoa($this->org, $igreja));
    atribuirPapel($this->org, $acessoAdmin, criarPapel($this->org, 'Administrador', [Permissao::OrganizacaoConfigurar]));

    $this->conta = criarContaVerificada(['email' => 'secretaria@exemplo.test', 'celular' => '+5511912340000']);
    criarAcesso($this->org, $this->conta, criarPessoa($this->org, $igreja));

    $this->pessoa = criarPessoa($this->org, $igreja, ['nome' => 'Joana Convidada']);
    criarContato($this->org, $this->pessoa, 'email', 'joana@exemplo.test');
    inserirComoDono('pessoa_contatos', [
        'organizacao_id' => $this->org, 'pessoa_id' => $this->pessoa, 'tipo' => 'celular', 'valor' => '+5511955550000', 'e_whatsapp' => true,
    ]);

    ligarNumeroWhatsApp($this->org);
    $this->travelTo(Carbon::now('America/Sao_Paulo')->setTime(10, 0));
    $this->whatsapp = app(MetaWhatsAppFalso::class);
});

function avisarNoLimite(object $teste, TipoAviso $tipo, Destinatario $destinatario, string $referencia): void
{
    noContexto($teste->org, null, fn () => app(Avisos::class)->registrar($tipo, [$destinatario], new Referencia('teste', $referencia)));
}

function limiteWhatsapp(int $organizacaoId, ?int $limite): void
{
    DB::connection('pgsql_dono')->table('organizacoes')->where('id', $organizacaoId)->update(['limite_whatsapp_mensal' => $limite]);
}

function usoJaRegistrado(int $organizacaoId, int $quantidade, string $categoria = 'servico'): void
{
    DB::connection('pgsql_dono')->table('uso_mensageria')->insert([
        'organizacao_id' => $organizacaoId,
        'mes' => Carbon::now('America/Sao_Paulo')->startOfMonth()->format('Y-m-d'),
        'canal' => 'whatsapp',
        'categoria' => $categoria,
        'quantidade' => $quantidade,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

/** @return array<string, int> "canal/categoria" => quantidade */
function usoDoMes(int $organizacaoId): array
{
    return DB::connection('pgsql_dono')->table('uso_mensageria')->where('organizacao_id', $organizacaoId)->get()
        ->mapWithKeys(fn (object $linha): array => ["{$linha->canal}/{$linha->categoria}" => (int) $linha->quantidade])
        ->all();
}

/** @return list<string> tipos dos avisos da conta */
function avisosDaConta(int $contaId): array
{
    return DB::connection('pgsql_dono')->table('avisos')->where('conta_id', $contaId)->orderBy('id')->pluck('tipo')->all();
}

it('soma o uso por mês, canal e categoria a cada envio aceito', function (): void {
    avisarNoLimite($this, TipoAviso::TransferenciaSolicitada, Destinatario::conta($this->conta, destino: '/t/1'), 'r1');
    avisarNoLimite($this, TipoAviso::TransferenciaSolicitada, Destinatario::conta($this->conta, destino: '/t/2'), 'r2');
    avisarNoLimite($this, TipoAviso::ConvitePainel, Destinatario::pessoa($this->pessoa, '/convites/abc'), 'r3');

    $linha = DB::connection('pgsql_dono')->table('uso_mensageria')->where('canal', 'whatsapp')->where('categoria', 'servico')->first();

    expect(usoDoMes($this->org))->toBe(['whatsapp/servico' => 2, 'email/convite' => 1, 'whatsapp/convite' => 1])
        ->and($linha->mes)->toBe(Carbon::now('America/Sao_Paulo')->startOfMonth()->format('Y-m-d'))
        ->and((array) $linha)->not->toHaveKeys(['destino', 'conteudo', 'conta_id']);
});

it('não corta o WhatsApp quando o limite é nulo', function (): void {
    limiteWhatsapp($this->org, null);
    usoJaRegistrado($this->org, 100000);

    avisarNoLimite($this, TipoAviso::TransferenciaSolicitada, Destinatario::conta($this->conta, destino: '/t/1'), 'r1');

    expect(DB::connection('pgsql_dono')->table('envios')->pluck('canal')->all())->toBe(['whatsapp'])
        ->and(avisosDaConta($this->admin))->toBe([]);
});

it('avisa quem configura a organização ao chegar a 80% do limite, uma vez no mês', function (): void {
    limiteWhatsapp($this->org, 10);
    usoJaRegistrado($this->org, 7);

    avisarNoLimite($this, TipoAviso::TransferenciaSolicitada, Destinatario::conta($this->conta, destino: '/t/1'), 'r1');
    avisarNoLimite($this, TipoAviso::TransferenciaSolicitada, Destinatario::conta($this->conta, destino: '/t/2'), 'r2');

    expect(avisosDaConta($this->admin))->toBe([TipoAviso::LimiteWhatsappAlerta->value])
        ->and(avisosDaConta($this->conta))->toHaveCount(2);

    // O aviso do limite vai por e-mail: não gasta a cota que anuncia.
    $canais = DB::connection('pgsql_dono')->table('envios as e')->join('avisos as a', 'a.id', '=', 'e.aviso_id')
        ->where('a.conta_id', $this->admin)->pluck('e.canal')->all();
    expect($canais)->toBe(['email']);
});

it('com 100% avisa o corte e o serviço deixa de usar WhatsApp, mas o convite continua', function (): void {
    limiteWhatsapp($this->org, 3);
    usoJaRegistrado($this->org, 2);

    // Este envio leva o uso a 3 (100%).
    avisarNoLimite($this, TipoAviso::TransferenciaSolicitada, Destinatario::conta($this->conta, destino: '/t/1'), 'r1');
    expect(avisosDaConta($this->admin))->toContain(TipoAviso::LimiteWhatsappAtingido->value);

    $antes = count($this->whatsapp->avisosEnviados);
    avisarNoLimite($this, TipoAviso::TransferenciaSolicitada, Destinatario::conta($this->conta, destino: '/t/2'), 'r2');
    $servico = DB::connection('pgsql_dono')->table('envios as e')->join('avisos as a', 'a.id', '=', 'e.aviso_id')
        ->where('a.referencia_id', 'r2')->pluck('e.canal')->all();
    expect($servico)->toBe(['email'])
        ->and(count($this->whatsapp->avisosEnviados))->toBe($antes);

    avisarNoLimite($this, TipoAviso::ConvitePainel, Destinatario::pessoa($this->pessoa, '/convites/abc'), 'r3');
    $convite = DB::connection('pgsql_dono')->table('envios as e')->join('avisos as a', 'a.id', '=', 'e.aviso_id')
        ->where('a.referencia_id', 'r3')->orderBy('e.id')->pluck('e.canal')->all();
    expect($convite)->toBe(['email', 'whatsapp']);
});

it('passa para o e-mail o envio de WhatsApp que já existia quando o limite foi atingido', function (): void {
    limiteWhatsapp($this->org, 1);
    usoJaRegistrado($this->org, 0);

    // Atinge o limite com um envio; o próximo de serviço nasce já no e-mail.
    avisarNoLimite($this, TipoAviso::TransferenciaDecidida, Destinatario::conta($this->conta, destino: '/t/1'), 'r1');
    expect(usoDoMes($this->org)['whatsapp/servico'])->toBe(1);

    avisarNoLimite($this, TipoAviso::TransferenciaDecidida, Destinatario::conta($this->conta, destino: '/t/2'), 'r2');
    expect(usoDoMes($this->org)['whatsapp/servico'])->toBe(1)
        ->and(usoDoMes($this->org)['email/servico'] ?? 0)->toBeGreaterThanOrEqual(1);
});
