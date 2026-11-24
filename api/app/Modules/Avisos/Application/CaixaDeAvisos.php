<?php

namespace App\Modules\Avisos\Application;

use App\Modules\Avisos\Domain\TipoAviso;
use Illuminate\Support\Facades\DB;

/**
 * Caixa de avisos da conta na organização em contexto (OIK-ADR-0008 §7; plan.md §7). A RLS limita à
 * organização ativa, e cada consulta filtra pela conta: ninguém lê nem marca o aviso de outra conta.
 * Pessoa sem conta não tem caixa. O texto é montado na leitura (TextosDaCaixa).
 */
final readonly class CaixaDeAvisos
{
    /** @return array{itens: list<array{uuid: string, tipo: TipoAviso, destino: string|null, lido_em: string|null, criado_em: string}>, total: int} */
    public function listar(int $contaId, int $pagina, int $porPagina, bool $soNaoLidos = false): array
    {
        $consulta = DB::table('avisos')
            ->where('conta_id', $contaId)
            ->when($soNaoLidos, fn ($q) => $q->whereNull('lido_em'));

        $total = (clone $consulta)->count();
        $linhas = $consulta
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->forPage($pagina, $porPagina)
            ->get(['uuid', 'tipo', 'destino', 'lido_em', 'created_at']);

        $itens = [];
        foreach ($linhas as $linha) {
            $tipo = TipoAviso::tryFrom((string) $linha->tipo);
            // Tipo que saiu do catálogo não tem texto: fica fora da caixa.
            if ($tipo === null) {
                continue;
            }
            $itens[] = [
                'uuid' => (string) $linha->uuid,
                'tipo' => $tipo,
                'destino' => $linha->destino === null ? null : (string) $linha->destino,
                'lido_em' => $linha->lido_em === null ? null : (string) $linha->lido_em,
                'criado_em' => (string) $linha->created_at,
            ];
        }

        return ['itens' => $itens, 'total' => $total];
    }

    public function naoLidos(int $contaId): int
    {
        return DB::table('avisos')->where('conta_id', $contaId)->whereNull('lido_em')->count();
    }

    /** @return bool false quando o aviso não é desta conta (ou não existe) */
    public function marcarLido(int $contaId, string $uuid): bool
    {
        $aviso = DB::table('avisos')->where('conta_id', $contaId)->where('uuid', $uuid)->first(['id', 'lido_em']);
        if ($aviso === null) {
            return false;
        }

        if ($aviso->lido_em === null) {
            DB::table('avisos')->where('id', $aviso->id)->update(['lido_em' => now(), 'updated_at' => now()]);
        }

        return true;
    }

    /** @return int quantos foram marcados agora */
    public function marcarTodos(int $contaId): int
    {
        return DB::table('avisos')
            ->where('conta_id', $contaId)
            ->whereNull('lido_em')
            ->update(['lido_em' => now(), 'updated_at' => now()]);
    }
}
