<?php

namespace App\Modules\Avisos\Application;

use RuntimeException;

/** Erro classificado pelo Adapter (OIK-ADR-0008 §3). A mensagem é um motivo curto, sem dado pessoal. */
abstract class FalhaDeEnvio extends RuntimeException {}
