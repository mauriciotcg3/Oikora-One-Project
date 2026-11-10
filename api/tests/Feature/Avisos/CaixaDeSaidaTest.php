<?php

use App\Modules\Avisos\Application\EntregarAviso;
use App\Modules\Avisos\Application\FalhaDefinitivaDeEnvio;
use App\Modules\Avisos\Application\FalhaTemporariaDeEnvio;
use App\Modules\Avisos\Application\Jobs\EnviarAviso;
use App\Modules\Avisos\Contracts\Avisos;
use App\Modules\Avisos\Contracts\Destinatario;
use App\Modules\Avisos\Contracts\Referencia;
use App\Modules\Avisos\Domain\TipoAviso;
use App\Modules\Avisos\Infrastructure\Canais\MetaWhatsApp\MetaWhatsAppFalso;
use App\Modules\Avisos\Infrastructure\Canais\Postmark\PostmarkFalso;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->org = criarOrganizacao('Avisos');
    $igreja = criarIgreja($this->org);
    $this->conta = criarContaVerificada(['email' => 'convidada@exemplo.test', 'celular' => '+5511912340000']);
    criarAcesso($this->org, $this->conta, criarPessoa($this->org, $igreja));
    $this->email = app(PostmarkFalso::class);
    $this->whatsapp = app(MetaWhatsAppFalso::class);
});

function registrarConvite(object $teste, string $referencia = 'convite-1'): void
{
    app(Avisos::class)->registrar(
        TipoAviso::ConvitePainel,
        [Destinatario::conta($teste->conta, destino: '/convites/abc')],
        new Referencia('convite', $referencia),
    );
}

/** @return list<object> */
function enviosComoDono(): array
{
    return DB::connection('pgsql_dono')->table('envios')->orderBy('id')->get()->all();
}

function contagemComoDono(string $tabela): int
{
    return DB::connection('pgsql_dono')->table($tabela)->count();
}

it('não deixa aviso, envio nem job quando a transação do fato faz rollback', function (): void {
    expect(fn () => noContexto($this->org, null, function (): void {
        registrarConvite($this);
        throw new RuntimeException('fato desfeito');
    }))->toThrow(RuntimeException::class, 'fato desfeito');

    expect(contagemComoDono('avisos'))->toBe(0)
        ->and(contagemComoDono('envios'))->toBe(0)
        ->and(contagemComoDono('jobs'))->toBe(0)
        ->and($this->email->avisosEnviados)->toBe([])
        ->and($this->whatsapp->avisosEnviados)->toBe([]);
});

it('grava o aviso e um envio pendente por canal no commit, sem chamar provedor antes dele', function (): void {
    Queue::fake();

    // O fake de fila ignora o afterCommit; "nada antes do commit" é provado com a fila síncrona real, abaixo.
    noContexto($this->org, null, fn () => registrarConvite($this));

    $envios = enviosComoDono();
    expect(contagemComoDono('avisos'))->toBe(1)
        ->and(array_column($envios, 'canal'))->toBe(['email', 'whatsapp'])
        ->and(array_unique(array_column($envios, 'estado')))->toBe(['pendente']);
    Queue::assertPushedOn('avisos', EnviarAviso::class);
    Queue::assertPushed(EnviarAviso::class, 2);
});

it('chama o provedor só depois do commit e marca o envio como enviado', function (): void {
    noContexto($this->org, null, function (): void {
        registrarConvite($this);
        expect($this->email->avisosEnviados)->toBe([]);
    });

    expect($this->email->avisosEnviados)->toHaveCount(1)
        ->and($this->email->avisosEnviados[0]->destino)->toBe('convidada@exemplo.test')
        ->and($this->whatsapp->avisosEnviados[0]->destino)->toBe('+5511912340000');

    $email = collect(enviosComoDono())->firstWhere('canal', 'email');
    expect($email->estado)->toBe('enviado')
        ->and($email->provedor)->toBe('postmark')
        ->and($email->destino_mascarado)->toBe('c***@exemplo.test')
        ->and($email->tentativas)->toBe(1);
});

it('registra um aviso só para a mesma tipo, referência, destinatário e chave', function (): void {
    Queue::fake();

    noContexto($this->org, null, fn () => registrarConvite($this));
    noContexto($this->org, null, fn () => registrarConvite($this));

    expect(contagemComoDono('avisos'))->toBe(1)
        ->and(contagemComoDono('envios'))->toBe(2);
    Queue::assertPushed(EnviarAviso::class, 2);
});

it('não chama o provedor de novo quando o job repete sobre envio já enviado', function (): void {
    noContexto($this->org, null, fn () => registrarConvite($this));
    $email = collect(enviosComoDono())->firstWhere('canal', 'email');

    noContexto($this->org, null, fn () => app(EntregarAviso::class)->executar((int) $email->id));
    EnviarAviso::dispatchSync((int) $email->id, $this->org);

    expect($this->email->avisosEnviados)->toHaveCount(1);
});

it('tenta de novo com 1 min, 5 min, 30 min e 2 h no erro temporário e desiste na quinta', function (): void {
    Queue::fake();
    noContexto($this->org, null, fn () => registrarConvite($this));
    $id = (int) collect(enviosComoDono())->firstWhere('canal', 'email')->id;
    $this->email->falharCom(new FalhaTemporariaDeEnvio('timeout'));

    foreach (EntregarAviso::ESPERAS as $espera) {
        $job = (new EnviarAviso($id, $this->org))->withFakeQueueInteractions();
        noContexto($this->org, null, fn () => $job->handle(app(EntregarAviso::class)));
        $job->assertReleased($espera);
        expect(collect(enviosComoDono())->firstWhere('id', $id)->estado)->toBe('falhou_temporario');
    }

    $job = (new EnviarAviso($id, $this->org))->withFakeQueueInteractions();
    noContexto($this->org, null, fn () => $job->handle(app(EntregarAviso::class)));
    $job->assertNotReleased();

    $envio = collect(enviosComoDono())->firstWhere('id', $id);
    expect($envio->estado)->toBe('falhou_definitivo')
        ->and($envio->erro_codigo)->toBe('tentativas_esgotadas')
        ->and($envio->tentativas)->toBe(5);
});

it('marca falhou_definitivo na hora no erro definitivo', function (): void {
    $this->email->falharCom(new FalhaDefinitivaDeEnvio('email_inexistente'));

    noContexto($this->org, null, fn () => registrarConvite($this));

    $email = collect(enviosComoDono())->firstWhere('canal', 'email');
    expect($email->estado)->toBe('falhou_definitivo')
        ->and($email->erro_codigo)->toBe('email_inexistente')
        ->and($email->tentativas)->toBe(1);
});

it('grava o aviso, registra envio.suprimido e não cria envio com a organização em migração', function (): void {
    capturarLog();
    Queue::fake();
    DB::connection('pgsql_dono')->table('organizacoes')->where('id', $this->org)->update(['estado' => 'em_migracao']);

    noContexto($this->org, null, fn () => registrarConvite($this));

    expect(contagemComoDono('avisos'))->toBe(1)
        ->and(contagemComoDono('envios'))->toBe(0);
    Queue::assertNothingPushed();

    $suprimido = collect(linhasDoLog())->firstWhere('mensagem', 'envio.suprimido');
    expect($suprimido['contexto']['motivo'])->toBe('migracao');
});

it('reenfileira no envios:varrer só os pendentes sem job há mais de 5 minutos', function (): void {
    Queue::fake();
    noContexto($this->org, null, fn () => registrarConvite($this, 'antigo'));
    $this->travel(6)->minutes();
    noContexto($this->org, null, fn () => registrarConvite($this, 'recente'));
    Queue::assertPushed(EnviarAviso::class, 4);

    Artisan::call('envios:varrer');

    Queue::assertPushed(EnviarAviso::class, 6);
    $antigos = DB::connection('pgsql_dono')->table('envios as e')
        ->join('avisos as a', 'a.id', '=', 'e.aviso_id')->where('a.referencia_id', 'antigo')->pluck('e.id')->all();
    Queue::assertPushed(EnviarAviso::class, fn (EnviarAviso $job): bool => in_array($job->envioId, $antigos, false));
});
