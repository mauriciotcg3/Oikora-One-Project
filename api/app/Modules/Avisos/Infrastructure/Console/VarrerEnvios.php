<?php

namespace App\Modules\Avisos\Infrastructure\Console;

use App\Modules\Avisos\Application\Jobs\EnviarAviso;
use App\Modules\Avisos\Application\ReenviarPeloProximoCanal;
use App\Modules\Avisos\Domain\EstadoEnvio;
use App\Modules\Avisos\Domain\TipoAviso;
use App\Modules\Organizacao\Contracts\ConsultaOrganizacoes;
use Illuminate\Console\Command;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * `envios:varrer`, a cada minuto (OIK-ADR-0008 §6, §12, §14), por organização e com contexto:
 * - reenfileira envios `pendente` sem job há mais de 5 min (o processo caiu entre o commit e o despacho);
 * - solta os `agendado` cuja hora chegou (janela diurna);
 * - reenvia pelo próximo canal o aviso de serviço cujo WhatsApp não confirmou entrega em 30 min.
 * Job a mais é inofensivo: o envio é travado e o estado conferido antes do provedor.
 */
final class VarrerEnvios extends Command
{
    private const LOTE = 500;

    protected $signature = 'envios:varrer {--minutos=5}';

    protected $description = 'Reenfileira envios pendentes sem job, solta agendados e reenvia WhatsApp sem entrega';

    public function __construct(
        private readonly ConsultaOrganizacoes $organizacoes,
        private readonly ReenviarPeloProximoCanal $reenvio,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $limite = now()->subMinutes(max(1, (int) $this->option('minutos')));
        $totais = ['reenfileirados' => 0, 'agendados' => 0, 'reenviados' => 0];

        foreach ($this->organizacoes->idsDisponiveis() as $organizacaoId) {
            DB::transaction(function () use ($organizacaoId, $limite, &$totais): void {
                DB::select("select set_config('app.organizacao_id', ?, true)", [(string) $organizacaoId]);

                $pendentes = $this->ids(DB::table('envios')
                    ->where('estado', EstadoEnvio::Pendente->value)
                    ->where('enfileirado_em', '<', $limite));
                $agendados = $this->ids(DB::table('envios')
                    ->where('estado', EstadoEnvio::Agendado->value)
                    ->where('agendado_para', '<=', now()));

                if ($pendentes !== []) {
                    DB::table('envios')->whereIn('id', $pendentes)->update(['enfileirado_em' => now(), 'updated_at' => now()]);
                }
                if ($agendados !== []) {
                    DB::table('envios')->whereIn('id', $agendados)->update([
                        'estado' => EstadoEnvio::Pendente->value, 'enfileirado_em' => now(), 'updated_at' => now(),
                    ]);
                }
                foreach ([...$pendentes, ...$agendados] as $id) {
                    EnviarAviso::dispatch($id, $organizacaoId)->afterCommit();
                }

                foreach ($this->whatsappSemEntrega() as $avisoId) {
                    if ($this->reenvio->reenviar($avisoId, 'whatsapp_sem_entrega') !== null) {
                        $totais['reenviados']++;
                    }
                }

                $totais['reenfileirados'] += count($pendentes);
                $totais['agendados'] += count($agendados);
            });
        }

        if ($totais['reenfileirados'] > 0) {
            Log::warning('envios.reenfileirados', ['quantidade' => $totais['reenfileirados']]);
        }
        $this->info(sprintf(
            '%d envio(s) reenfileirado(s), %d agendado(s) solto(s), %d reenvio(s) pelo próximo canal.',
            $totais['reenfileirados'], $totais['agendados'], $totais['reenviados'],
        ));

        return self::SUCCESS;
    }

    /** @return list<int> */
    private function ids(Builder $consulta): array
    {
        return array_values($consulta->orderBy('id')->limit(self::LOTE)->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all());
    }

    /**
     * Avisos de tipo com reenvio cujo WhatsApp está só `enviado` há mais de 30 min e que ainda não têm
     * envio pelo próximo canal. `entregue` e `lido` chegam pelo webhook (T-19).
     *
     * @return list<int>
     */
    private function whatsappSemEntrega(): array
    {
        return array_values(DB::table('envios as e')
            ->join('avisos as a', 'a.id', '=', 'e.aviso_id')
            ->where('e.canal', 'whatsapp')
            ->where('e.estado', EstadoEnvio::Enviado->value)
            ->where('e.enviado_em', '<', now()->subMinutes(ReenviarPeloProximoCanal::ESPERA_ENTREGA_WHATSAPP_MINUTOS))
            ->whereIn('a.tipo', TipoAviso::queReenviam())
            ->whereNotExists(fn ($outro) => $outro->selectRaw('1')->from('envios as x')
                ->whereColumn('x.aviso_id', 'e.aviso_id')
                ->where('x.canal', '<>', 'whatsapp'))
            ->orderBy('e.id')
            ->limit(self::LOTE)
            ->pluck('e.aviso_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all());
    }
}
