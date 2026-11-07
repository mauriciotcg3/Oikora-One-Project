<?php

namespace App\Modules\Identidade\Application;

use Illuminate\Contracts\Cache\Repository;

/**
 * Limite de login por identificador e por IP, com bloqueio que dobra a cada violação
 * (OIK-ADR-0003 §4). Os números vêm de `config/oikora.php` (U-22).
 */
final class LimiteDeTentativas
{
    public function __construct(private readonly Repository $cache) {}

    /** Segundos que faltam para liberar; 0 quando não há bloqueio. */
    public function segundosDeBloqueio(string $identificadorResumo, string $ipResumo): int
    {
        $agora = now()->getTimestamp();

        return max(
            0,
            (int) $this->cache->get($this->chave('id', $identificadorResumo, 'bloqueado_ate'), 0) - $agora,
            (int) $this->cache->get($this->chave('ip', $ipResumo, 'bloqueado_ate'), 0) - $agora,
        );
    }

    public function registrarFalha(string $identificadorResumo, string $ipResumo): void
    {
        $this->contar('id', $identificadorResumo, (int) config('oikora.login.tentativas_por_identificador'));
        $this->contar('ip', $ipResumo, (int) config('oikora.login.tentativas_por_ip'));
    }

    /** O sucesso limpa só o identificador: o contador do IP continua protegendo as outras contas. */
    public function liberarIdentificador(string $identificadorResumo): void
    {
        $this->cache->forget($this->chave('id', $identificadorResumo, 'falhas'));
        $this->cache->forget($this->chave('id', $identificadorResumo, 'violacoes'));
    }

    private function contar(string $tipo, string $resumo, int $limite): void
    {
        $falhas = $this->chave($tipo, $resumo, 'falhas');

        $this->cache->add($falhas, 0, (int) config('oikora.login.janela_segundos'));

        if ((int) $this->cache->increment($falhas) < $limite) {
            return;
        }

        $this->cache->forget($falhas);
        $this->bloquear($tipo, $resumo);
    }

    private function bloquear(string $tipo, string $resumo): void
    {
        $chaveViolacoes = $this->chave($tipo, $resumo, 'violacoes');
        $violacoes = (int) $this->cache->get($chaveViolacoes, 0) + 1;
        $this->cache->put($chaveViolacoes, $violacoes, (int) config('oikora.login.memoria_violacoes_segundos'));

        $duracao = (int) min(
            (int) config('oikora.login.bloqueio_inicial_segundos') * 2 ** ($violacoes - 1),
            (int) config('oikora.login.bloqueio_maximo_segundos'),
        );

        $this->cache->put($this->chave($tipo, $resumo, 'bloqueado_ate'), now()->getTimestamp() + $duracao, $duracao);
    }

    private function chave(string $tipo, string $resumo, string $dado): string
    {
        return "login:{$tipo}:{$resumo}:{$dado}";
    }
}
