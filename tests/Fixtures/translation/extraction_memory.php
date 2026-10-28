<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use Bblslug\Translation\HtmlTextDocument;
use Bblslug\Translation\TextDocument;

$kind = $argv[1] ?? '';
$html = $kind === 'html-markers';
$prepareOnly = $kind === 'long-marker-prepare';
if ($prepareOnly) {
    $source = str_repeat('1', 24 * 1024 * 1024);
    // Build in place so fixture concatenations do not hide the extraction peak.
    foreach (str_split('Start @@') as $index => $character) {
        $source[$index] = $character;
    }
    foreach (str_split('@@ Finish') as $index => $character) {
        $source[strlen($source) - 9 + $index] = $character;
    }
} else {
    $middle = match ($kind) {
        'lines' => str_repeat("\t \r\n", 300000),
        'text-markers' => str_repeat('@@0@@ ', 300000),
        default => str_repeat('&#64;&#64;0&#64;&#64; ', 300000),
    };
    $source = $html ? '<p>Start ' . $middle . 'Finish</p>' : "Start\r\n" . $middle . 'Finish ';
}
$document = $html ? new HtmlTextDocument($source) : new TextDocument($source);
$payload = $document->getJson();
if (!$document->hasTexts() || $payload !== '{"s0":"Start","s1":"Finish"}') {
    throw new RuntimeException('Sequential extraction changed endpoint segments');
}
if (!$prepareOnly) {
    if ($document->restore($payload) !== $source) {
        throw new RuntimeException('Sequential extraction changed source bytes');
    }
    $expected = $html ? '<p>Inicio ' . $middle . 'Fin</p>' : "Inicio\r\n" . $middle . 'Fin ';
    if ($document->restore('{"s0":"Inicio","s1":"Fin"}') !== $expected) {
        throw new RuntimeException('Sequential extraction changed translated reconstruction');
    }
    if ($document->getJson() !== $payload || $document->restore($payload) !== $source) {
        throw new RuntimeException('Sequential extraction changed repeated use');
    }
}
echo json_encode([
    'bytes' => strlen($source),
    'count' => 2,
    'phase' => $prepareOnly ? 'prepared' : 'restored',
    'peak' => memory_get_peak_usage(true),
], JSON_THROW_ON_ERROR);
