<?php

namespace App\Modules\Pessoas\Application\Jobs;

use App\Modules\Auditoria\Application\Auditoria;
use App\Modules\Auditoria\Contracts\OperacoesDesfaziveis;
use App\Modules\Auditoria\Domain\EventoAuditoria;
use App\Modules\Autorizacao\Contracts\AlcanceAtual;
use App\Modules\Pessoas\Application\Lixeira\ExclusaoBloqueada;
use App\Modules\Pessoas\Application\Lixeira\Lixeira;
use App\Modules\Pessoas\Application\Lixeira\RemovedorDePessoas;
use App\Shared\Tenancy\Jobs\ContextoOrganizacaoJob;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Purga diária da lixeira de uma organização (OIK-ADR-0005 §7, §8; RN-19; U-33), com o contexto dela
 * (OIK-ADR-0008 §5). Fichas excluídas há mais de 30 dias passam pelo removedor no modo configurado; a
 * absorvida por mesclagem fica enquanto a operação estiver `executada`. Sem o modo em produção, não
 * remove nada e registra `lgpd.erasure.blocked_no_policy`.
 */
final class PurgarLixeira implements ShouldQueue
{
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $organizacao_id)
    {
        $this->onQueue('default');
    }

    /** @return list<object> */
    public function middleware(): array
    {
        return [new ContextoOrganizacaoJob];
    }

    public function handle(AlcanceAtual $alcance, Lixeira $lixeira, RemovedorDePessoas $removedor, OperacoesDesfaziveis $operacoes, Auditoria $auditoria): void
    {
        try {
            $removedor->modo();
        } catch (ExclusaoBloqueada) {
            return;
        }

        $alcance->comoSistema($this->organizacao_id, function () use ($lixeira, $removedor, $operacoes, $auditoria): void {
            $protegidas = $operacoes->protegidos('mesclagem', 'absorvida_id');
            $removidas = 0;

            foreach ($lixeira->vencidas((int) config('lgpd.dias_lixeira', 30), $protegidas) as $pessoaId => $uuid) {
                $modo = $removedor->remover($pessoaId, 'purga');
                $auditoria->registrar(
                    evento: EventoAuditoria::PessoaPurgada,
                    organizacaoId: $this->organizacao_id,
                    alvoTipo: 'pessoa',
                    alvoId: $uuid,
                    campos: [$modo],
                    canal: 'sistema',
                );
                $removidas++;
            }

            Log::info('pessoas.lixeira.purgada', ['removidas' => $removidas]);
        });
    }
}
