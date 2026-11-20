<?php

namespace App\Modules\Avisos\Application;

use App\Modules\Avisos\Domain\TipoAviso;
use Illuminate\Support\Facades\DB;

/**
 * Preferências conta × organização × tipo × canal, ligadas por padrão (OIK-ADR-0008 §13). Tipo
 * obrigatório não se desliga: no convite nenhum canal sai; no serviço a pessoa só troca o canal externo,
 * então ao menos um precisa continuar ligado.
 */
final readonly class PreferenciasDeAviso
{
    /** @return list<string> canais do tipo, na ordem dele, sem os desligados pela conta */
    public function ligados(int $contaId, TipoAviso $tipo): array
    {
        $desligados = DB::table('preferencias_aviso')
            ->where('conta_id', $contaId)
            ->where('tipo', $tipo->value)
            ->where('ligado', false)
            ->pluck('canal')
            ->all();

        return array_values(array_diff($tipo->canais(), $desligados));
    }

    /** @throws PreferenciaRecusada */
    public function definir(int $contaId, TipoAviso $tipo, string $canal, bool $ligado): void
    {
        if (! in_array($canal, $tipo->canais(), true)) {
            throw new PreferenciaRecusada('canal_invalido');
        }

        if (! $ligado && $tipo->obrigatorio()) {
            $restantes = array_diff($this->ligados($contaId, $tipo), [$canal]);
            if (! $tipo->permiteEscolherCanal() || $restantes === []) {
                throw new PreferenciaRecusada('tipo_obrigatorio');
            }
        }

        DB::table('preferencias_aviso')->upsert(
            [[
                'organizacao_id' => (int) DB::scalar('select app_organizacao_atual()'),
                'conta_id' => $contaId,
                'tipo' => $tipo->value,
                'canal' => $canal,
                'ligado' => $ligado,
                'created_at' => now(),
                'updated_at' => now(),
            ]],
            ['organizacao_id', 'conta_id', 'tipo', 'canal'],
            ['ligado', 'updated_at'],
        );
    }
}
