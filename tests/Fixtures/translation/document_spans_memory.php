<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use Bblslug\Translation\HtmlTextDocument;
use Bblslug\Translation\TextDocument;

$html = ($argv[1] ?? '') === 'html';
$source = $html
    ? str_repeat("<p title='Hint'>Hello &amp; world</p>", 50000)
    : str_repeat("Hello world \r\n", 100000);
$document = $html ? new HtmlTextDocument($source) : new TextDocument($source);
$payload = $document->getJson();
if (!$document->hasTexts() || $document->getJson() !== $payload) {
    throw new RuntimeException('Document span memory fixture changed its payload');
}
$count = count(get_object_vars(json_decode($payload, false, 512, JSON_THROW_ON_ERROR)));
if ($document->restore($payload) !== $source) {
    throw new RuntimeException('Document span memory fixture changed source spelling or whitespace');
}
$translated = $html
    ? str_replace(['"Hint"', '"Hello & world"'], [
        json_encode('Info "x" &', JSON_THROW_ON_ERROR), '"Hola & mundo"',
    ], $payload)
    : str_replace('"Hello world"', '"Hallo Welt"', $payload);
$expected = $html
    ? str_repeat("<p title='Info &quot;x&quot; &amp;'>Hola &amp; mundo</p>", 50000)
    : str_repeat("Hallo Welt \r\n", 100000);
if ($document->restore($translated) !== $expected || $document->restore($payload) !== $source) {
    throw new RuntimeException('Document span memory fixture changed translated restoration or reuse');
}
echo json_encode([
    'bytes' => strlen($source),
    'count' => $count,
    'peak' => memory_get_peak_usage(true),
], JSON_THROW_ON_ERROR);
