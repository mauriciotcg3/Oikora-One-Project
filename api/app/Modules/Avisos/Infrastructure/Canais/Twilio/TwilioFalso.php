<?php

namespace App\Modules\Avisos\Infrastructure\Canais\Twilio;

use App\Modules\Avisos\Application\CanalDeSms;
use App\Modules\Avisos\Infrastructure\Canais\CanalFalso;

final class TwilioFalso extends CanalFalso implements CanalDeSms {}
