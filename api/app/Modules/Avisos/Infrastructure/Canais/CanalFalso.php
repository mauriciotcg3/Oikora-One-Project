<?php

namespace App\Modules\Avisos\Infrastructure\Canais;

use App\Modules\Avisos\Application\CanalDeCodigo;
use App\Modules\Avisos\Application\MensagemDeCodigo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Adapter sem provedor real, usado até a integração de cada canal existir (OIK-ADR-0010 §2).
 * No dev, grava a mensagem em `storage/app/private/codigos-dev.log`, para o código poder ser lido no navegador.
 */
abstract class CanalFalso implements CanalDeCodigo
{
    /** @var list<MensagemDeCodigo> */
    public array $enviados = [];

    private ?Throwable $falha = null;

    public function falharCom(Throwable $falha): void
    {
        $this->falha = $falha;
    }

    public function voltarAoNormal(): void
    {
        $this->falha = null;
    }

    public function enviar(MensagemDeCodigo $mensagem): string
    {
        if ($this->falha !== null) {
            throw $this->falha;
        }

        $this->enviados[] = $mensagem;

        if (app()->environment('local')) {
            Storage::disk('local')->append('codigos-dev.log', sprintf(
                '%s %s %s %s %s',
                now()->toDateTimeString(),
                class_basename($this),
                $mensagem->finalidade,
                $mensagem->destino,
                $mensagem->codigo,
            ));
        }

        return 'falso-'.Str::uuid();
    }
}
