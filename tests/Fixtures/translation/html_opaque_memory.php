<?php

declare(strict_types=1);

require dirname(__DIR__, 3) . '/vendor/autoload.php';

use Bblslug\Translation\HtmlTextDocument;

$profile = $argv[1] ?? '';
$unit = match ($profile) {
    'markers' => "@@0@@ Hello \n",
    'opaque' => "<pre>@@0@@ fixed</pre><code>keep</code><svg/> Hello @@1@@ \n",
    default => throw new InvalidArgumentException('Unknown HTML protection profile'),
};
$copies = intdiv(1024 * 1024, strlen($unit));
$source = str_repeat($unit, $copies);
$document = new HtmlTextDocument($source);
$payload = $document->getJson();
$values = json_decode($payload, true, 512, JSON_THROW_ON_ERROR);
if (count($values) !== $copies || array_unique(array_values($values)) !== ['Hello']) {
    throw new RuntimeException('Opaque content or markers reached the translation payload');
}
if ($document->restore($payload) !== $source) {
    throw new RuntimeException('HTML protection changed source bytes');
}
foreach ($values as &$value) {
    $value = 'Bonjour';
}
unset($value);
$result = $document->restoreValues($values);
if (
    $result !== str_replace('Hello', 'Bonjour', $source)
    || $document->restore($payload) !== $source
) {
    throw new RuntimeException('HTML protection changed translation or repeated restoration');
}
echo json_encode([
    'bytes' => strlen($source), 'segments' => $copies, 'peak' => memory_get_peak_usage(true),
    'payloadHash' => hash('sha256', $payload), 'resultHash' => hash('sha256', $result),
], JSON_THROW_ON_ERROR);
