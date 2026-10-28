<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use Bblslug\Filters\FilterManager;

$text = str_repeat('@@0@@ ', 180000);
$json = ($argv[1] ?? '') === 'json';
$source = $json ? json_encode(['body' => $text], JSON_THROW_ON_ERROR) : $text;
unset($text);
$filters = new FilterManager(['url']);
$prepared = $filters->apply($source);
if ($json) {
    json_decode($prepared, true, 512, JSON_THROW_ON_ERROR);
}
if ($filters->restore($prepared) !== $source) {
    throw new RuntimeException('Dense marker fixture changed source bytes');
}
echo json_encode([
    'bytes' => strlen($source),
    'captures' => $filters->getStats()[0]['count'],
    'peak' => memory_get_peak_usage(true),
], JSON_THROW_ON_ERROR);
