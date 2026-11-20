<?php

namespace App\Modules\Autorizacao\Infrastructure;

use App\Modules\Autorizacao\Application\AtribuicoesDePermissao;
use App\Modules\Autorizacao\Contracts\PapeisDaConta;
use App\Modules\Autorizacao\Domain\EscopoTipo;
use App\Modules\Organizacao\Contracts\CatalogoIgrejas;
use Illuminate\Support\Facades\DB;

final readonly class PapeisDaContaPostgres implements PapeisDaConta
{
    public function __construct(
        private AtribuicoesDePermissao $atribuicoes,
        private CatalogoIgrejas $igrejas,
    ) {}

    public function administrador(int $contaId): bool
    {
        $linha = DB::selectOne('select autorizacao_conta_tem_papel_sistema(?, ?) as tem', [$contaId, 'administrador']);

        return (bool) ($linha->tem ?? false);
    }

    public function capacidades(int $organizacaoId, int $acessoId): array
    {
        $linhas = $this->atribuicoes->doAcesso($organizacaoId, $acessoId);
        $porTipo = static fn (EscopoTipo $tipo): array => array_values(array_unique(array_filter(array_map(
            static fn (array $linha): ?int => $linha['escopo_tipo'] === $tipo->value ? $linha['escopo_id'] : null,
            $linhas,
        ))));
        $uuids = [
            EscopoTipo::Igreja->value => $this->igrejas->resumo($porTipo(EscopoTipo::Igreja)),
            EscopoTipo::Regional->value => $this->igrejas->resumoRegionais($porTipo(EscopoTipo::Regional)),
        ];

        /** @var array<string, array{permissao: string, escopo_tipo: string, escopo_ids: list<string>}> $capacidades */
        $capacidades = [];
        foreach ($linhas as $linha) {
            $chave = $linha['permissao'].'|'.$linha['escopo_tipo'];
            $capacidades[$chave] ??= ['permissao' => $linha['permissao'], 'escopo_tipo' => $linha['escopo_tipo'], 'escopo_ids' => []];

            $uuid = $linha['escopo_id'] === null ? null : ($uuids[$linha['escopo_tipo']][$linha['escopo_id']]['uuid'] ?? null);
            if ($uuid !== null && ! in_array($uuid, $capacidades[$chave]['escopo_ids'], true)) {
                $capacidades[$chave]['escopo_ids'][] = $uuid;
            }
        }

        ksort($capacidades);

        return array_values($capacidades);
    }
}
