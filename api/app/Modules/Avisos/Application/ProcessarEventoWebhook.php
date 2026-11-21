<?php

namespace App\Modules\Avisos\Application;

use App\Modules\Pessoas\Contracts\ContatosDaPessoa;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final readonly class ProcessarEventoWebhook
{
    /** @var array<string, int> */
    private const ORDEM = [
        'pendente' => 0,
        'agendado' => 1,
        'enviando' => 2,
        'enviado' => 3,
        'entregue' => 4,
        'lido' => 5,
    ];

    public function __construct(
        private EnviosExternos $localizador,
        private ContatosDaPessoa $contatos,
        private ReenviarPeloProximoCanal $reenvio,
    ) {}

    public function executar(string $provedor, string $tipo, string $idExterno, ?string $erroCodigo): void
    {
        $identidade = $this->localizador->localizar($provedor, $idExterno);
        if ($identidade === null) {
            Log::info('webhook.envio_desconhecido', ['provedor' => $provedor, 'evento' => $tipo]);

            return;
        }

        DB::transaction(function () use ($identidade, $tipo, $erroCodigo, $provedor): void {
            DB::select("select set_config('app.organizacao_id', ?, true)", [(string) $identidade->organizacaoId]);

            $envio = DB::table('envios')
                ->join('avisos', function ($join): void {
                    $join->on('avisos.id', '=', 'envios.aviso_id')
                        ->on('avisos.organizacao_id', '=', 'envios.organizacao_id');
                })
                ->select(['envios.*', 'avisos.pessoa_id'])
                ->where('envios.id', $identidade->id)
                ->where('envios.aviso_criado_em', $identidade->avisoCriadoEm)
                ->lockForUpdate()
                ->first();
            if ($envio === null) {
                return;
            }

            if ($tipo === 'reclamacao_spam') {
                $this->descadastrarPorSpam($envio->pessoa_id, $envio->canal);
                Log::info('webhook.reclamacao_spam_processada', ['provedor' => $provedor]);

                return;
            }

            if (! $this->podeAvancar((string) $envio->estado, $tipo)) {
                return;
            }

            $agora = now();
            $alteracoes = ['estado' => $tipo, 'updated_at' => $agora];
            $campoData = match ($tipo) {
                'enviado' => 'enviado_em',
                'entregue' => 'entregue_em',
                'lido' => 'lido_em',
                'falhou_definitivo' => 'falhou_em',
                default => null,
            };
            if ($campoData !== null) {
                $alteracoes[$campoData] = $agora;
            }
            if ($tipo === 'falhou_definitivo') {
                $alteracoes['erro_codigo'] = $erroCodigo;
            }

            DB::table('envios')
                ->where('id', $envio->id)
                ->where('aviso_criado_em', $envio->aviso_criado_em)
                ->update($alteracoes);

            if ($tipo === 'falhou_definitivo') {
                if ($envio->pessoa_id !== null) {
                    $this->contatos->marcarFalhaDeEntrega((int) $envio->pessoa_id, (string) $envio->canal);
                }
                $this->reenvio->reenviar((int) $envio->aviso_id, $erroCodigo ?? 'webhook_falha_definitiva');
            }

            Log::info('webhook.envio_atualizado', ['provedor' => $provedor, 'evento' => $tipo]);
        });
    }

    private function podeAvancar(string $atual, string $novo): bool
    {
        if ($novo === 'falhou_definitivo') {
            return ! in_array($atual, ['falhou_definitivo', 'suprimido', 'cancelado', 'entregue', 'lido'], true);
        }

        return isset(self::ORDEM[$atual], self::ORDEM[$novo]) && self::ORDEM[$novo] > self::ORDEM[$atual];
    }

    private function descadastrarPorSpam(mixed $pessoaId, mixed $canal): void
    {
        if ($pessoaId === null) {
            return;
        }

        $uuid = $this->contatos->uuid((int) $pessoaId);
        if ($uuid !== null) {
            $this->contatos->descadastrar($uuid, (string) $canal);
        }
    }
}
