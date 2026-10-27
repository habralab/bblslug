<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use Bblslug\Translation\HtmlTextDocument;

$entities = ($argv[1] ?? '') === 'entities';
$unit = $entities ? '&#x41;&amp;&#x1F600; ' : 'word ';
$half = str_repeat($unit, intdiv(2 * 1024 * 1024, strlen($unit)));
$source = '<p>Start ' . $half . '&#64;&#64;7&#64;&#64;' . $half . ' End</p>';
unset($half);
$document = new HtmlTextDocument($source);
$payload = $document->getJson();
if ($document->restore($payload) !== $source) {
    throw new RuntimeException('HTML memory fixture changed source bytes');
}
echo json_encode([
    'bytes' => strlen($source),
    'segments' => count(get_object_vars(json_decode($payload, false, 512, JSON_THROW_ON_ERROR))),
    'peak' => memory_get_peak_usage(true),
], JSON_THROW_ON_ERROR);
