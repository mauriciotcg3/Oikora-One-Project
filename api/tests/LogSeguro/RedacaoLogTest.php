<?php

use App\Shared\Observabilidade\Log\RedacaoDeDados;
use Illuminate\Support\Facades\Log;

/** Dados-sentinela do CA-28: nenhum deles pode aparecer numa linha de log. */
const SENTINELAS = [
    'cpf' => '123.456.789-09',
    'cpf_sem_pontos' => '12345678909',
    'celular' => '+55 (11) 98765-4321',
    'celular_cru' => '5511987654321',
    'email' => 'sentinela.maria@exemplo.test',
    'token' => 'tok_9f8e7d6c5b4a39281706f5e4d3c2b1a0ffeeddcc',
    'codigo' => '482915',
];

beforeEach(function (): void {
    capturarLog();
});

it('redige os dados-sentinela da mensagem', function (string $nome, string $valor): void {
    Log::warning("falha ao processar {$valor} agora");

    $bruto = conteudoDoLog();
    expect($bruto)->not->toContain($valor)
        ->and($bruto)->toContain(RedacaoDeDados::MARCA);
})->with(fn (): array => collect(SENTINELAS)->map(fn (string $v, string $k): array => [$k, $v])->values()->all());

it('redige os dados-sentinela do contexto, por chave e por padrão, em qualquer nível', function (): void {
    Log::error('envio.falhou', [
        'email' => SENTINELAS['email'],
        'detalhe' => ['destino_informado' => 'ligar para '.SENTINELAS['celular'], 'cpf' => SENTINELAS['cpf']],
        'Authorization' => 'Bearer '.SENTINELAS['token'],
        'resposta_do_provedor' => 'código '.SENTINELAS['codigo'].' recusado para '.SENTINELAS['cpf_sem_pontos'],
        'senha' => 'qualquer-coisa',
    ]);

    $bruto = conteudoDoLog();
    foreach ([...array_values(SENTINELAS), 'qualquer-coisa'] as $valor) {
        expect($bruto)->not->toContain($valor);
    }
});

it('redige a mensagem de exceção no contexto', function (): void {
    Log::error('erro.inesperado', ['exception' => new RuntimeException('cpf '.SENTINELAS['cpf'].' duplicado')]);

    expect(conteudoDoLog())->not->toContain(SENTINELAS['cpf'])
        ->and(conteudoDoLog())->toContain('RuntimeException');
});

it('preserva UUIDs, que não são dado pessoal', function (): void {
    Log::info('pessoa.lida', ['pessoa' => '0b9d4c1e-6f7a-4b2c-9d8e-1f2a3b4c5d6e']);

    expect(conteudoDoLog())->toContain('0b9d4c1e-6f7a-4b2c-9d8e-1f2a3b4c5d6e');
});
