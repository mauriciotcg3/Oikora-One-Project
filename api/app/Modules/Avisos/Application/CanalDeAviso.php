<?php

namespace App\Modules\Avisos\Application;

/** Adapter de um canal externo para avisos (OIK-ADR-0008 §1). Classifica o erro (§3). */
interface CanalDeAviso
{
    /**
     * @return string id da mensagem no provedor
     *
     * @throws FalhaDefinitivaDeEnvio
     * @throws FalhaTemporariaDeEnvio
     */
    public function enviarAviso(MensagemDeAviso $mensagem): string;

    /** Nome gravado em `envios.provedor`. */
    public function provedor(): string;
}
