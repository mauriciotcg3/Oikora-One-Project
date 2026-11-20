<?php

namespace App\Modules\Avisos\Infrastructure\Canais;

use App\Modules\Avisos\Application\FalhaDefinitivaDeEnvio;
use App\Modules\Avisos\Application\FalhaTemporariaDeEnvio;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Base dos Adapters HTTP de canal (OIK-ADR-0008 §3): timeout curto, classificação do erro em
 * temporário ou definitivo e log estruturado sem destino, código, token ou corpo da resposta.
 */
abstract class CanalHttp
{
    /** Timeout total da chamada: o código vale 10 min e a fila tenta de novo em 1 min. */
    protected const TIMEOUT_SEGUNDOS = 10;

    protected const CONEXAO_SEGUNDOS = 3;

    abstract public function provedor(): string;

    /**
     * Classifica uma resposta de erro do provedor. Devolve o motivo curto (sem dado pessoal) e se
     * vale tentar de novo.
     *
     * @return array{0: string, 1: bool} [motivo, temporário]
     */
    abstract protected function classificar(Response $resposta): array;

    protected function cliente(): PendingRequest
    {
        return Http::timeout(self::TIMEOUT_SEGUNDOS)
            ->connectTimeout(self::CONEXAO_SEGUNDOS)
            ->acceptJson();
    }

    /**
     * @param  Closure(PendingRequest): Response  $chamada
     *
     * @throws FalhaDefinitivaDeEnvio
     * @throws FalhaTemporariaDeEnvio
     */
    protected function chamar(string $operacao, Closure $chamada): Response
    {
        $inicio = hrtime(true);

        try {
            $resposta = $chamada($this->cliente());
        } catch (ConnectionException) {
            $this->registrar($operacao, $inicio, 'falhou_temporario', 'timeout');

            throw new FalhaTemporariaDeEnvio('timeout');
        }

        if ($resposta->successful()) {
            $this->registrar($operacao, $inicio, 'enviado', null, $resposta->status());

            return $resposta;
        }

        [$motivo, $temporario] = $this->classificar($resposta);
        $this->registrar($operacao, $inicio, $temporario ? 'falhou_temporario' : 'falhou_definitivo', $motivo, $resposta->status());

        throw $temporario ? new FalhaTemporariaDeEnvio($motivo) : new FalhaDefinitivaDeEnvio($motivo);
    }

    /** Status HTTP que valem nova tentativa quando o provedor não manda código próprio. */
    protected function statusTemporario(Response $resposta): bool
    {
        return $resposta->status() === 429 || $resposta->serverError();
    }

    /**
     * Lê uma credencial ou parâmetro do provedor; ausente é defeito de configuração, não carga.
     *
     * @throws FalhaDefinitivaDeEnvio
     */
    protected function configuracao(string $chave): string
    {
        $valor = config($chave);
        if (! is_string($valor) || $valor === '') {
            throw new FalhaDefinitivaDeEnvio('provedor_nao_configurado');
        }

        return $valor;
    }

    protected function opcional(string $chave): ?string
    {
        $valor = config($chave);

        return is_string($valor) && $valor !== '' ? $valor : null;
    }

    protected function textoDoCodigo(string $codigo, string $finalidade): string
    {
        $para = match (true) {
            str_starts_with($finalidade, 'verificar') => 'confirmar seu contato',
            str_starts_with($finalidade, 'recuperar') => 'recuperar sua senha',
            default => 'entrar',
        };

        return sprintf('Seu código Oikora para %s: %s. Vale por 10 minutos. Não compartilhe com ninguém.', $para, $codigo);
    }

    private function registrar(string $operacao, int $inicio, string $estado, ?string $erro, ?int $http = null): void
    {
        Log::info('avisos.provedor.chamada', [
            'module' => 'Avisos',
            'action' => $operacao,
            'provedor' => $this->provedor(),
            'status' => $estado,
            'http_status' => $http,
            'error' => $erro,
            'duration' => (int) round((hrtime(true) - $inicio) / 1_000_000),
        ]);
    }
}
