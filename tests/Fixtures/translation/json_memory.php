<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use Bblslug\Filters\FilterManager;

$matched = ($argv[1] ?? '') === 'nested';
$escaped = str_repeat('\\u0061', intdiv(4 * 1024 * 1024, 6));
$tail = $matched ? ' https:\\/\\/example.org\\/path?q=\\u00e9 '
    . '\\u003Ccode\\u003Efixed\\u003C\\/code\\u003E' : '';
$inner = '{"body":"' . $escaped . $tail . '"}';
$source = $matched ? '{"outer":' . json_encode($inner, JSON_THROW_ON_ERROR) . '}' : $inner;
unset($escaped, $inner);
$filters = new FilterManager(['url', 'html_code']);
$prepared = $filters->apply($source);
if ($filters->restore($prepared) !== $source) {
    throw new RuntimeException('JSON memory fixture changed source escapes');
}
echo json_encode([
    'bytes' => strlen($source),
    'counts' => array_column($filters->getStats(), 'count'),
    'peak' => memory_get_peak_usage(true),
], JSON_THROW_ON_ERROR);
