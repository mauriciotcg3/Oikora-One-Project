<?php

namespace App\Modules\Pessoas\Application\Actions;

use App\Modules\Auditoria\Application\Auditoria;
use App\Modules\Auditoria\Domain\EventoAuditoria;
use App\Modules\Autorizacao\Contracts\AlcanceAtual;
use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Pessoas\Application\Jobs\GerarExportacao;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class SolicitarExportacao
{
    public function __construct(private AlcanceAtual $alcance, private Auditoria $auditoria) {}

    /**
     * @param  array<string, mixed>  $filtro
     * @param  list<string>  $selecionados
     * @param  list<string>  $categorias
     */
    public function executar(string $tipo, array $filtro, array $selecionados, array $categorias, string $formato): ?string
    {
        $ator = $this->alcance->ator();
        $metadados = ['filtro' => array_keys($filtro), 'categorias' => $categorias, 'motivo_codigo' => 'authz.denied'];
        if ($this->alcance->igrejasCom(Permissao::PessoasExportar) === []) {
            $this->auditoria->registrarNegacaoDeRegra(
                EventoAuditoria::ExportacaoNegada,
                $ator->organizacaoId,
                'exportacao',
                'negada',
                $ator->acessoId,
                $ator->contaId,
                metadados: $metadados,
            );

            return null;
        }
        $uuid = (string) Str::uuid();
        DB::transaction(function () use ($ator, $uuid, $tipo, $filtro, $selecionados, $categorias, $formato): void {
            DB::table('exportacoes')->insert([
                'uuid' => $uuid, 'organizacao_id' => $ator->organizacaoId, 'acesso_id' => $ator->acessoId,
                'conta_id' => $ator->contaId, 'tipo' => $tipo, 'filtro' => json_encode((object) $filtro, JSON_THROW_ON_ERROR),
                'ids_selecionados' => json_encode($selecionados, JSON_THROW_ON_ERROR), 'categorias' => json_encode($categorias, JSON_THROW_ON_ERROR),
                'formato' => $formato, 'estado' => 'pendente', 'expira_em' => now()->addDay(), 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->auditoria->registrar(EventoAuditoria::ExportacaoPedida, $ator->organizacaoId, 'exportacao', $uuid, $ator->acessoId, $ator->contaId,
                metadados: ['filtro' => array_keys($filtro), 'categorias' => $categorias, 'contagem' => count($selecionados)]);
            GerarExportacao::dispatch($ator->organizacaoId, $uuid)->onQueue('pesadas')->afterCommit();
        });

        return $uuid;
    }
}
