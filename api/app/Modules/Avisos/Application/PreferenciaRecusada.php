<?php

namespace App\Modules\Avisos\Application;

use DomainException;

/** Preferência que a regra do tipo não permite (`tipo_obrigatorio`, `canal_invalido`). */
final class PreferenciaRecusada extends DomainException {}
