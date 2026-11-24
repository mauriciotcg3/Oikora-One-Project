<?php

namespace App\Modules\Auditoria\Application\Consultas;

use App\Modules\Autorizacao\Contracts\AlcanceAtual;
use App\Modules\Autorizacao\Domain\Permissao;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Consulta da auditoria (CA-12; RN-15; OIK-ADR-0005 §3): só com `auditoria.ver`, sob a RLS da organização
 * ativa e pelo alcance (OIK-ADR-0004 §4). Com a permissão no escopo organização, vê tudo; em escopo menor,
 * só eventos das igrejas do alcance — evento sem igreja (governança) fica de fora. Devolve nomes de campo
 * e metadados codificados, que é tudo o que a tabela guarda: nunca valores.
 *
 * @phpstan-type LinhaAuditoria array{id: int, ocorrido_em: string, evento: string, acesso_id: int|null, operador_id: int|null, alvo_tipo: string, alvo_id: string, igreja_id: int|null, campos: list<string>, metadados: array<string, mixed>, canal: string, request_id: string|null, resultado: string, codigo_falha: string|null, trace_id: string|null}
 */
final readonly class ConsultarAuditoria
{
    public function __construct(private AlcanceAtual $alcance) {}

    public function permitido(): bool
    {
        if ($this->alcance->igrejasCom(Permissao::AuditoriaVer) !== [] || $this->alcance->temNaOrganizacao(Permissao::AuditoriaVer)) {
            return true;
        }

        Log::info('authz.denied', ['permissao' => Permissao::AuditoriaVer->value]);

        return false;
    }

    /** @return array{itens: list<LinhaAuditoria>, total: int} */
    public function executar(FiltroAuditoria $filtro): array
    {
        $consulta = $this->filtrar(DB::table('auditoria'), $filtro);
        $total = (clone $consulta)->count();

        $linhas = $consulta
            ->orderByDesc('ocorrido_em')
            ->orderByDesc('id')
            ->forPage($filtro->pagina, $filtro->porPagina)
            ->get(['id', 'ocorrido_em', 'evento', 'acesso_id', 'operador_id', 'alvo_tipo', 'alvo_id', 'igreja_id', 'campos', 'metadados', 'canal', 'request_id', 'resultado', 'codigo_falha', 'trace_id']);

        $itens = [];
        foreach ($linhas as $linha) {
            /** @var list<string> $campos */
            $campos = json_decode((string) $linha->campos, true, flags: JSON_THROW_ON_ERROR);
            /** @var array<string, mixed> $metadados */
            $metadados = json_decode((string) $linha->metadados, true, flags: JSON_THROW_ON_ERROR);

            $itens[] = [
                'id' => (int) $linha->id,
                'ocorrido_em' => (string) $linha->ocorrido_em,
                'evento' => (string) $linha->evento,
                'acesso_id' => $linha->acesso_id === null ? null : (int) $linha->acesso_id,
                'operador_id' => $linha->operador_id === null ? null : (int) $linha->operador_id,
                'alvo_tipo' => (string) $linha->alvo_tipo,
                'alvo_id' => (string) $linha->alvo_id,
                'igreja_id' => $linha->igreja_id === null ? null : (int) $linha->igreja_id,
                'campos' => $campos,
                'metadados' => $metadados,
                'canal' => (string) $linha->canal,
                'request_id' => $linha->request_id === null ? null : (string) $linha->request_id,
                'resultado' => (string) $linha->resultado,
                'codigo_falha' => $linha->codigo_falha === null ? null : (string) $linha->codigo_falha,
                'trace_id' => $linha->trace_id === null ? null : (string) $linha->trace_id,
            ];
        }

        return ['itens' => $itens, 'total' => $total];
    }

    private function filtrar(Builder $consulta, FiltroAuditoria $filtro): Builder
    {
        if (! $this->alcance->temNaOrganizacao(Permissao::AuditoriaVer)) {
            $consulta->whereIn('igreja_id', $this->alcance->igrejasCom(Permissao::AuditoriaVer));
        }

        return $consulta
            ->when($filtro->de !== null, fn (Builder $q) => $q->where('ocorrido_em', '>=', $filtro->de))
            ->when($filtro->ate !== null, fn (Builder $q) => $q->where('ocorrido_em', '<', $filtro->ate))
            ->when($filtro->evento !== null, fn (Builder $q) => $q->where('evento', $filtro->evento))
            ->when($filtro->atorAcessoId !== null, fn (Builder $q) => $q->where('acesso_id', $filtro->atorAcessoId))
            ->when($filtro->igrejaId !== null, fn (Builder $q) => $q->where('igreja_id', $filtro->igrejaId))
            ->when($filtro->alvoTipo !== null, fn (Builder $q) => $q->where('alvo_tipo', $filtro->alvoTipo))
            ->when($filtro->alvoId !== null, fn (Builder $q) => $q->where('alvo_id', $filtro->alvoId))
            ->when($filtro->busca !== null, fn (Builder $q) => $q->where(function (Builder $busca) use ($filtro): void {
                $termo = '%'.$filtro->busca.'%';
                $busca->where('evento', 'ilike', $termo)
                    ->orWhere('alvo_tipo', 'ilike', $termo)
                    ->orWhere('alvo_id', 'ilike', $termo);
            }))
            ->when($filtro->resultado !== null, fn (Builder $q) => $q->where('resultado', $filtro->resultado));
    }
}
