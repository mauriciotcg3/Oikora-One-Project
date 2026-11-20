<?php

namespace App\Modules\Identidade\Presentation;

use Illuminate\Http\Request;

/** Conta que passou da senha e espera o segundo fator. Fica na sessão, sem autenticar ninguém. */
final readonly class DesafioPendente
{
    private const CHAVE = 'dois_fatores';

    private function __construct(
        public int $contaId,
        public string $identificadorResumo,
        public int $reenvios,
        public ?string $entrada,
    ) {}

    public static function abrir(Request $request, int $contaId, string $identificadorResumo, ?string $entrada = null): void
    {
        $request->session()->put(self::CHAVE, [
            'conta_id' => $contaId,
            'identificador_resumo' => $identificadorResumo,
            'reenvios' => 0,
            'entrada' => $entrada,
            'expira_em' => now()->addMinutes((int) config('oikora.codigos.desafio_minutos'))->getTimestamp(),
        ]);
    }

    /** A senha não vale para sempre: um desafio antigo não pode pedir código novo. */
    public static function ler(Request $request): ?self
    {
        /** @var array{conta_id: int, identificador_resumo: string, reenvios: int, entrada?: string|null, expira_em: int}|null $dados */
        $dados = $request->session()->get(self::CHAVE);

        if ($dados === null || $dados['expira_em'] <= now()->getTimestamp()) {
            return null;
        }

        return new self($dados['conta_id'], $dados['identificador_resumo'], $dados['reenvios'], $dados['entrada'] ?? null);
    }

    public static function contarReenvio(Request $request): void
    {
        $request->session()->increment(self::CHAVE.'.reenvios');
    }

    public static function fechar(Request $request): void
    {
        $request->session()->forget(self::CHAVE);
    }
}
