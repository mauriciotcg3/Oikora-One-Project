<?php

namespace App\Modules\Avisos\Infrastructure\Canais\Postmark;

use App\Modules\Avisos\Application\CanalDeEmail;
use App\Modules\Avisos\Infrastructure\Canais\CanalFalso;

final class PostmarkFalso extends CanalFalso implements CanalDeEmail {}
