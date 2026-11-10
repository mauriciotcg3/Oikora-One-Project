<?php

namespace App\Shared\Observabilidade\Log;

use Illuminate\Support\Facades\Context;
use Monolog\Formatter\FormatterInterface;
use Monolog\LogRecord;
use Throwable;

/**
 * Uma linha JSON por registro, com os campos do Architecture Standard §21 (OIK-ADR-0010 §9; OBS-002).
 * Mensagem e contexto passam pela redação antes de sair; os campos de correlação vêm do Context.
 */
final class FormatoJson implements FormatterInterface
{
    private readonly RedacaoDeDados $redacao;

    public function __construct()
    {
        $this->redacao = new RedacaoDeDados;
    }

    public function format(LogRecord $record): string
    {
        [$modulo, $acao] = $this->origem();

        $linha = [
            'ts' => $record->datetime->format('Y-m-d\TH:i:s.uP'),
            'nivel' => strtolower($record->level->getName()),
            'mensagem' => $this->redacao->texto($record->message),
            'ambiente' => (string) config('app.env'),
            'module' => $modulo,
            'action' => $acao,
            'request_id' => $this->textoDoContext('request_id'),
            'trace_id' => $this->textoDoContext('trace_id'),
            'correlation_id' => $this->textoDoContext('correlation_id'),
            'cliente' => $this->textoDoContext('cliente'),
            'canal' => $record->channel,
        ];

        if ($record->context !== []) {
            $linha['contexto'] = $this->redacao->dados($record->context);
        }

        return json_encode($linha, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR)."\n";
    }

    /** @param  array<LogRecord>  $records */
    public function formatBatch(array $records): string
    {
        return implode('', array_map($this->format(...), $records));
    }

    /** @return array{0: string|null, 1: string|null} */
    private function origem(): array
    {
        $modulo = $this->textoDoContext('module');
        $acao = $this->textoDoContext('action');

        // Job ou rotina já disseram de onde vêm; a rota só vale fora deles.
        if ($modulo !== null || $acao !== null) {
            return [$modulo, $acao];
        }

        try {
            $rota = app()->bound('request') ? request()->route() : null;
        } catch (Throwable) {
            $rota = null;
        }

        if ($rota === null) {
            return [null, null];
        }

        return Origem::daAcao($rota->getActionName());
    }

    private function textoDoContext(string $chave): ?string
    {
        $valor = Context::get($chave);

        return is_string($valor) ? $valor : null;
    }
}
