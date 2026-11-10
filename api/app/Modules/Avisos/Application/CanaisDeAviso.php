<?php

namespace App\Modules\Avisos\Application;

/** Escolhe o Adapter de cada canal de aviso (`email`, `whatsapp`). */
interface CanaisDeAviso
{
    public function doCanal(string $canal): CanalDeAviso;
}
