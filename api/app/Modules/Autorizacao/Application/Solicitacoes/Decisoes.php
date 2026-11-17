<?php

namespace App\Modules\Autorizacao\Application\Solicitacoes;

use App\Modules\Auditoria\Domain\EventoAuditoria;
use App\Modules\Autorizacao\Application\Actions\Governanca;
use App\Modules\Autorizacao\Application\Actions\Resultado;
use App\Modules\Autorizacao\Application\AntiEscalada;
use App\Modules\Autorizacao\Domain\ItemSolicitado;
use App\Modules\Autorizacao\Domain\Permissao;
use Illuminate\Support\Facades\Log;

/**
 * Partes comuns das decisões sobre solicitações (OIK-ADR-0012 §4, §8): quem decide é administrador com
 * `permissoes.solicitacoes.aprovar` e teto que cubra cada permissão no escopo pedido. Toda transição é
 * auditada; o log técnico leva ids e estado, nunca justificativa.
 */
final readonly class Decisoes
{
    public function __construct(
        public Governanca $governanca,
        private AntiEscalada $antiEscalada,
        private PapelDoAprovador $papel,
    ) {}

    public function decisorNegado(): ?Resultado
    {
        if ($negada = $this->governanca->negada(Permissao::SolicitacoesPermissaoAprovar)) {
            return $negada;
        }

        $ator = $this->governanca->alcance->ator();
        if (! $this->papel->administrador($ator->organizacaoId, $ator->acessoId)) {
            Log::info('authz.denied', ['permissao' => Permissao::SolicitacoesPermissaoAprovar->value, 'motivo' => 'nao_administrador']);

            return Resultado::falha(403, 'authz.denied');
        }

        return null;
    }

    /**
     * Teto do aprovador (anti-escalada): cada item precisa estar no alcance de quem decide.
     *
     * @param  list<ItemSolicitado>  $itens
     */
    public function foraDoTeto(array $itens, string $alvoUuid): ?Resultado
    {
        foreach ($itens as $item) {
            if ($this->antiEscalada->semCobertura([$item->permissao->value], $item->escopoTipo, $item->escopoId) !== []) {
                $this->governanca->auditar(EventoAuditoria::EscaladaBloqueada, 'solicitacao_permissao', $alvoUuid, [
                    'motivo_codigo' => 'teto_do_aprovador',
                    'permissoes' => [$item->permissao->value],
                    'escopo_tipo' => $item->escopoTipo->value,
                    'escopo_id' => $item->escopoId,
                ]);

                return Resultado::falha(403, 'escalada_bloqueada');
            }
        }

        return null;
    }

    /**
     * Metadados de auditoria com as categorias sensíveis explícitas (OIK-ADR-0012 §5).
     *
     * @param  list<ItemSolicitado>  $itens
     * @return array{permissoes: list<string>, escopos: list<string>, categorias_sensiveis: list<string>}
     */
    public static function metadados(array $itens): array
    {
        return [
            'permissoes' => array_map(static fn (ItemSolicitado $i): string => $i->permissao->value, $itens),
            'escopos' => array_map(static fn (ItemSolicitado $i): string => $i->escopo(), $itens),
            'categorias_sensiveis' => array_values(array_unique(array_filter(
                array_map(static fn (ItemSolicitado $i): ?string => $i->permissao->categoriaSensivel(), $itens),
            ))),
        ];
    }

    public static function registrarLog(string $evento, string $uuid, string $estado): void
    {
        Log::info($evento, ['module' => 'Autorizacao', 'entity' => 'solicitacao_permissao', 'entity_id' => $uuid, 'status' => $estado]);
    }
}
