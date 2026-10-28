<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

$installation = $argv[1];
spl_autoload_register(static function (string $class) use ($installation): void {
    if (str_starts_with($class, 'Bblslug\\')) {
        $path = $installation . '/src/' . str_replace('\\', '/', $class) . '.php';
        if (is_file($path)) {
            require $path;
        }
    }
}, true, true);
ob_start();
require __DIR__ . '/checkpoint.php';
$output = json_decode((string) ob_get_clean(), true, 512, JSON_THROW_ON_ERROR);
$output['libraryPath'] = (new ReflectionClass(\Bblslug\Bblslug::class))->getFileName();
echo json_encode($output, JSON_THROW_ON_ERROR);
