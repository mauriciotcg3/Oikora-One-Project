<?php

namespace App\Modules\Identidade\Application;

use Illuminate\Contracts\Session\Session;

/**
 * Estado do painel guardado na sessão do servidor (plan.md §3.2): organização ativa e elevação.
 * Nunca vem de parâmetro livre; cada requisição revalida a organização contra os acessos ativos.
 */
final class SessaoDoPainel
{
    private const ORGANIZACAO = 'painel.organizacao_id';

    private const ELEVADA_EM = 'painel.elevada_em';

    private const ULTIMO_USO = 'painel.ultimo_uso_em';

    public static function organizacao(Session $sessao): ?int
    {
        $valor = $sessao->get(self::ORGANIZACAO);

        return is_int($valor) ? $valor : null;
    }

    public static function escolherOrganizacao(Session $sessao, int $organizacaoId): void
    {
        $sessao->put(self::ORGANIZACAO, $organizacaoId);
    }

    public static function esquecerOrganizacao(Session $sessao): void
    {
        $sessao->forget(self::ORGANIZACAO);
    }

    public static function elevar(Session $sessao): void
    {
        $agora = now()->getTimestamp();
        $sessao->put(self::ELEVADA_EM, $agora);
        $sessao->put(self::ULTIMO_USO, $agora);
    }

    /**
     * Confere a elevação e renova o uso. Expira com 2h sem uso ou 12h no total (OIK-ADR-0003 §9).
     */
    public static function usarElevacao(Session $sessao): bool
    {
        $elevadaEm = $sessao->get(self::ELEVADA_EM);
        $ultimoUso = $sessao->get(self::ULTIMO_USO);
        $agora = now()->getTimestamp();

        $valida = is_int($elevadaEm) && is_int($ultimoUso)
            && $agora - $ultimoUso < (int) config('oikora.painel.elevacao_ociosa_minutos') * 60
            && $agora - $elevadaEm < (int) config('oikora.painel.elevacao_total_horas') * 3600;

        if (! $valida) {
            $sessao->forget([self::ELEVADA_EM, self::ULTIMO_USO]);

            return false;
        }

        $sessao->put(self::ULTIMO_USO, $agora);

        return true;
    }

    /** @return array{ativa: bool, expira_em: int|null} sem renovar o uso */
    public static function estadoDaElevacao(Session $sessao): array
    {
        $elevadaEm = $sessao->get(self::ELEVADA_EM);
        $ultimoUso = $sessao->get(self::ULTIMO_USO);

        if (! is_int($elevadaEm) || ! is_int($ultimoUso)) {
            return ['ativa' => false, 'expira_em' => null];
        }

        $expiraEm = min(
            $ultimoUso + (int) config('oikora.painel.elevacao_ociosa_minutos') * 60,
            $elevadaEm + (int) config('oikora.painel.elevacao_total_horas') * 3600,
        );

        return $expiraEm > now()->getTimestamp()
            ? ['ativa' => true, 'expira_em' => $expiraEm]
            : ['ativa' => false, 'expira_em' => null];
    }
}
