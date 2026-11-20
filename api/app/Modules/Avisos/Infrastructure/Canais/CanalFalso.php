<?php

namespace App\Modules\Avisos\Infrastructure\Canais;

use App\Modules\Avisos\Application\CanalDeAviso;
use App\Modules\Avisos\Application\CanalDeCodigo;
use App\Modules\Avisos\Application\MensagemDeAviso;
use App\Modules\Avisos\Application\MensagemDeCodigo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Adapter sem provedor real, usado até a integração de cada canal existir (OIK-ADR-0010 §2).
 * No dev, grava a mensagem em `storage/app/private/codigos-dev.log`, para o código poder ser lido no navegador.
 */
abstract class CanalFalso implements CanalDeAviso, CanalDeCodigo
{
    /** @var list<MensagemDeCodigo> */
    public array $enviados = [];

    /** @var list<MensagemDeAviso> */
    public array $avisosEnviados = [];

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

    public function enviarAviso(MensagemDeAviso $mensagem): string
    {
        if ($this->falha !== null) {
            throw $this->falha;
        }

        $this->avisosEnviados[] = $mensagem;

        return 'falso-'.Str::uuid();
    }

    public function provedor(): string
    {
        return strtolower(str_replace('Falso', '', class_basename($this)));
    }
}
