<?php

declare(strict_types=1);

use App\App;

require dirname(__DIR__) . '/vendor/autoload.php';

App::boot(dirname(__DIR__))->run();
