<?php

namespace App\Modules\Avisos\Application;

interface CanalDeCodigo
{
    /**
     * @return string id da mensagem no provedor
     *
     * @throws FalhaDefinitivaDeEnvio
     * @throws FalhaTemporariaDeEnvio
     */
    public function enviar(MensagemDeCodigo $mensagem): string;
}
