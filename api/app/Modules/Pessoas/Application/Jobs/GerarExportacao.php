<?php

namespace App\Modules\Pessoas\Application\Jobs;

use App\Modules\Auditoria\Application\Auditoria;
use App\Modules\Auditoria\Domain\EventoAuditoria;
use App\Modules\Autorizacao\Contracts\AlcanceAtual;
use App\Modules\Autorizacao\Contracts\Ator;
use App\Modules\Autorizacao\Domain\Permissao;
use App\Modules\Avisos\Contracts\Avisos;
use App\Modules\Avisos\Contracts\Destinatario;
use App\Modules\Avisos\Contracts\Referencia;
use App\Modules\Avisos\Domain\TipoAviso;
use App\Modules\Identidade\Contracts\AcessosDaOrganizacao;
use App\Modules\Pessoas\Application\Exportacoes\ArquivosDeExportacao;
use App\Modules\Pessoas\Application\Exportacoes\DadosDeExportacao;
use App\Shared\Tenancy\Jobs\ContextoOrganizacaoJob;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;

final class GerarExportacao implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    public function __construct(public readonly int $organizacao_id, public readonly string $exportacaoUuid)
    {
        $this->onQueue('pesadas');
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [new ContextoOrganizacaoJob];
    }

    public function handle(AlcanceAtual $alcance, AcessosDaOrganizacao $acessos, DadosDeExportacao $dados, ArquivosDeExportacao $arquivos, Auditoria $auditoria, Avisos $avisos): void
    {
        $exportacao = DB::table('exportacoes')->where('uuid', $this->exportacaoUuid)->lockForUpdate()->first();
        if ($exportacao === null || ! in_array($exportacao->estado, ['pendente', 'processando'], true)) {
            return;
        }
        /** @var object{id: int, uuid: string, acesso_id: int, conta_id: int, tipo: string, filtro: string, ids_selecionados: string, categorias: string, formato: string, estado: string, expira_em: string} $exportacao */
        $contas = $acessos->contasAtivas([(int) $exportacao->acesso_id]);
        if (($contas[(int) $exportacao->acesso_id] ?? null) !== (int) $exportacao->conta_id) {
            $this->negar($exportacao, $auditoria, 'acesso_revogado');

            return;
        }
        $alcance->definir(new Ator($this->organizacao_id, (int) $exportacao->conta_id, (int) $exportacao->acesso_id));
        try {
            $igrejas = $alcance->igrejasCom(Permissao::PessoasExportar);
            if ($igrejas === []) {
                $this->negar($exportacao, $auditoria, 'permissao_revogada');

                return;
            }
            DB::table('exportacoes')->where('id', $exportacao->id)->update(['estado' => 'processando', 'updated_at' => now()]);
            $filtro = (array) json_decode((string) $exportacao->filtro, true, flags: JSON_THROW_ON_ERROR);
            $selecionados = array_values(array_filter((array) json_decode($exportacao->ids_selecionados, true, flags: JSON_THROW_ON_ERROR), 'is_string'));
            $categorias = array_values(array_filter((array) json_decode($exportacao->categorias, true, flags: JSON_THROW_ON_ERROR), 'is_string'));
            $projecao = $dados->obter((string) $exportacao->tipo, $filtro, $selecionados, $categorias, $igrejas,
                $alcance->igrejasCom(Permissao::ContatosVer), $alcance->igrejasCom(Permissao::FeVer));
            $caminho = $arquivos->gravar($this->organizacao_id, (string) $exportacao->uuid, (string) $exportacao->formato, $projecao['cabecalhos'], $projecao['linhas']);
            $contagem = count($projecao['linhas']);
            DB::table('exportacoes')->where('id', $exportacao->id)->update(['estado' => 'concluida', 'arquivo_path' => $caminho, 'contagem' => $contagem, 'concluida_em' => now(), 'updated_at' => now()]);
            $auditoria->registrar(EventoAuditoria::ExportacaoConcluida, $this->organizacao_id, 'exportacao', $this->exportacaoUuid, (int) $exportacao->acesso_id, (int) $exportacao->conta_id,
                metadados: ['filtro' => $this->chavesDoFiltro($filtro), 'categorias' => $categorias, 'contagem' => $contagem], canal: 'job');
            $avisos->registrar(TipoAviso::ExportacaoPronta, [Destinatario::conta((int) $exportacao->conta_id, destino: '/painel/exportacoes/'.$this->exportacaoUuid)], new Referencia('exportacao', $this->exportacaoUuid));
            ExpirarExportacao::dispatch($this->organizacao_id, $this->exportacaoUuid)
                ->delay(CarbonImmutable::parse((string) $exportacao->expira_em))
                ->onQueue('pesadas')
                ->afterCommit();
        } finally {
            $alcance->limpar();
        }
    }

    /** @param object{id: int, acesso_id: int, conta_id: int, categorias: string, filtro: string} $exportacao */
    private function negar(object $exportacao, Auditoria $auditoria, string $motivo): void
    {
        DB::table('exportacoes')->where('id', $exportacao->id)->update(['estado' => 'negada', 'motivo_codigo' => $motivo, 'updated_at' => now()]);
        $categorias = array_values(array_filter((array) json_decode($exportacao->categorias, true, flags: JSON_THROW_ON_ERROR), 'is_string'));
        $filtro = (array) json_decode($exportacao->filtro, true, flags: JSON_THROW_ON_ERROR);
        $auditoria->registrar(EventoAuditoria::ExportacaoNegada, $this->organizacao_id, 'exportacao', $this->exportacaoUuid, (int) $exportacao->acesso_id, (int) $exportacao->conta_id,
            metadados: ['filtro' => $this->chavesDoFiltro($filtro), 'categorias' => $categorias, 'motivo_codigo' => $motivo], canal: 'job');
    }

    /**
     * @param  array<mixed, mixed>  $filtro
     * @return list<string>
     */
    private function chavesDoFiltro(array $filtro): array
    {
        return array_values(array_filter(array_keys($filtro), 'is_string'));
    }
}
