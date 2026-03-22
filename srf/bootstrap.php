<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

foreach (glob(__DIR__ . '/src/*.php') ?: [] as $file) {
    require_once $file;
}
