<?php

declare(strict_types=1);

namespace Bblslug\Tools\Smoke;

/** Deterministic large and abbreviation-boundary inputs for live smoke checks. */
final class Fixtures
{
    /** @return array<string,array<string,array<string,mixed>>> */
    public static function cases(): array
    {
        $large = self::largeParagraph();
        $abbreviations = implode(' ', array_fill(0, 2, self::abbreviationText()));

        return [
            'large' => self::formatCases(
                $large,
                'EN',
                'DE',
                [],
                2000,
                ['markers' => ['RPT-A17' => 29, 'RPT-B19' => 29, 'RPT-C23' => 29, 'TAIL-541' => 1]]
            ),
            'abbreviations' => self::formatCases(
                $abbreviations,
                'RU',
                'EN',
                ['ул.', 'Проф.'],
                180,
                ['expectedIvanovCount' => 2, 'expectedStreetCount' => 2]
            ),
        ];
    }

    /** @param array<string,mixed> $case @return array<string,bool> */
    public static function check(array $case, string $output): array
    {
        if (trim($output) === '' || $output === $case['input']) {
            throw new \RuntimeException('Empty or untranslated fixture result');
        }

        $content = $output;
        if (($case['format'] ?? '') === 'json') {
            $decodedOutput = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
            if (!is_string($decodedOutput['body'] ?? null)) {
                throw new \RuntimeException('Selected JSON HTML value is not a string');
            }
            $content = $decodedOutput['body'];
        }
        $checks = ['translated' => true];
        $expectations = $case['expectations'];
        if (isset($expectations['markers'])) {
            foreach ($expectations['markers'] as $marker => $count) {
                if (substr_count($content, $marker) !== $count) {
                    throw new \RuntimeException('Large fixture marker count changed: ' . $marker);
                }
            }
            $tail = strpos($content, 'TAIL-541');
            $lastRepeated = max(
                strrpos($content, 'RPT-A17'),
                strrpos($content, 'RPT-B19'),
                strrpos($content, 'RPT-C23')
            );
            if ($tail === false || $lastRepeated === false || $tail < $lastRepeated) {
                throw new \RuntimeException('Large fixture tail marker moved before repeated content');
            }
            $checks['allMarkersPreserved'] = true;
            $checks['tailAfterRepeatedContent'] = true;
        }

        if (isset($expectations['expectedIvanovCount'])) {
            preg_match_all('/I\.\s*I\.\s*Ivanov/i', $content, $ivanovs);
            preg_match_all('/Lenin(?:a)?\s+(?:St\.?|Street)\b/i', $content, $streets);
            if (count($ivanovs[0]) !== $expectations['expectedIvanovCount']) {
                throw new \RuntimeException('Abbreviation fixture Ivanov count changed');
            }
            if (count($streets[0]) !== $expectations['expectedStreetCount']) {
                throw new \RuntimeException('Abbreviation fixture street reference count changed');
            }
            $checks['reviewedAbbreviationFactsPreserved'] = true;
        }

        if (($case['format'] ?? '') === 'text') {
            preg_match_all('/\r\n|\r|\n/', $case['input'], $sourceLines);
            preg_match_all('/\r\n|\r|\n/', $output, $targetLines);
            if ($sourceLines[0] !== $targetLines[0]) {
                throw new \RuntimeException('Text fixture line separators changed');
            }
            $edges = static function (string $text): array {
                $result = [];
                foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
                    preg_match('/\A([ \t\x{00A0}]*).*?([ \t\x{00A0}]*)\z/u', $line, $match);
                    $result[] = [$match[1], $match[2]];
                }
                return $result;
            };
            if ($edges($case['input']) !== $edges($output)) {
                throw new \RuntimeException('Text fixture line whitespace changed');
            }
            $checks['lineSeparatorsPreserved'] = true;
        }
        if (isset($expectations['lineEdges'])) {
            $edges = $expectations['lineEdges'];
            if (!str_starts_with($output, $edges['prefix']) || !str_ends_with($output, $edges['suffix'])) {
                throw new \RuntimeException('Text fixture line separators or edge whitespace changed');
            }
            $checks['lineEdgesPreserved'] = true;
        }

        if (isset($expectations['protectedUrl'])) {
            if (
                substr_count($content, $expectations['protectedUrl'])
                !== substr_count($case['input'], $expectations['protectedUrl'])
            ) {
                throw new \RuntimeException('Fixture URL changed');
            }
            $checks['urlPreserved'] = true;
        }

        if (isset($expectations['protectedBlocks'])) {
            foreach ($expectations['protectedBlocks'] as $block) {
                if (!str_contains($content, $block)) {
                    throw new \RuntimeException('Protected HTML block changed');
                }
            }
            $tags = static function (string $text): array {
                preg_match_all('/<!--.*?-->|<[^>]+>/s', $text, $matches);
                return $matches[0];
            };
            $sourceHtml = $case['format'] === 'json'
                ? json_decode($case['input'], true, 512, JSON_THROW_ON_ERROR)['body'] : $case['input'];
            if ($tags($sourceHtml) !== $tags($content)) {
                throw new \RuntimeException('HTML tag bytes or order changed');
            }
            foreach ($expectations['requiredTags'] as $tag) {
                if (!str_contains($content, $tag)) {
                    throw new \RuntimeException('HTML container or attribute changed: ' . $tag);
                }
            }
            $checks['htmlStructurePreserved'] = true;
            $checks['protectedBlocksPreserved'] = true;
        }

        if (isset($expectations['jsonPrefix'])) {
            $prefix = $expectations['jsonPrefix'];
            $suffix = $expectations['jsonSuffix'];
            if (!str_starts_with($output, $prefix) || !str_ends_with($output, $suffix)) {
                throw new \RuntimeException('Unselected JSON lexical content changed');
            }
            $middle = substr($output, strlen($prefix), strlen($output) - strlen($prefix) - strlen($suffix));
            if (!is_string(json_decode($middle, true, 512, JSON_THROW_ON_ERROR))) {
                throw new \RuntimeException('Selected JSON span is not a string');
            }
            $decoded = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
            if (!is_string($decoded['body'] ?? null)) {
                throw new \RuntimeException('Selected JSON HTML value is not a string');
            }
            $checks['unselectedJsonLexemesPreserved'] = true;
            $checks['selectedJsonStringValid'] = true;
        }

        return $checks;
    }

    /** @param list<string> $hints @param array<string,mixed> $expectations
     *  @return array<string,array<string,mixed>>
     */
    private static function formatCases(
        string $source,
        string $sourceLang,
        string $targetLang,
        array $hints,
        int $cap,
        array $expectations
    ): array {
        $html = self::html($source);
        $jsonPrefix = '{"prefix":900719925474099312345,"decimal":1.2300,"false":false,"empty":null,'
            . '"path":' . json_encode('C:\\tmp\\x', JSON_THROW_ON_ERROR) . ',"unicode":"\u00e9","body":';
        $jsonSuffix = ' ,"suffix":"untouched"}';
        $json = $jsonPrefix . json_encode($html, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR)
            . $jsonSuffix;
        $formats = [];
        foreach (['text' => $source, 'html' => $html, 'json' => $json] as $format => $input) {
            $formatExpectations = $expectations;
            if ($format === 'text' && $sourceLang === 'EN') {
                $formatExpectations['lineEdges'] = ['prefix' => "\t\u{00A0}\r\n", 'suffix' => "\r\n\t\u{00A0}"];
            }
            if ($format === 'text') {
                $formatExpectations['protectedUrl'] = 'https://example.org/docs?a=1&b=2';
            }
            if ($format !== 'text') {
                $formatExpectations['protectedUrl'] = 'https://example.org/docs?a=1&amp;b=2';
                $formatExpectations['protectedBlocks'] = self::protectedBlocks();
                $formatExpectations['requiredTags'] = [
                    '<article data-case="fixture">', '<p class="body">',
                    '<a href="https://example.org/docs?a=1&amp;b=2">',
                ];
            }
            if ($format === 'json') {
                $formatExpectations['jsonPrefix'] = $jsonPrefix;
                $formatExpectations['jsonSuffix'] = $jsonSuffix;
            }
            if (!str_contains($input, $formatExpectations['protectedUrl'])) {
                unset($formatExpectations['protectedUrl']);
            }
            $formats[$format] = [
                'format' => $format,
                'input' => $input,
                'filters' => $format === 'text' ? ['url'] : ['url', 'html_code'],
                'sourceLang' => $sourceLang,
                'targetLang' => $targetLang,
                'hints' => $hints,
                'cap' => $cap,
                'htmlJsonPointers' => $format === 'json' ? ['/body'] : [],
                'expectations' => $formatExpectations,
            ];
        }
        return $formats;
    }

    private static function largeParagraph(): string
    {
        $sentences = [
            'The gateway reports stable latency for marker RPT-A17.',
            'The worker records a clean retry boundary for marker RPT-B19.',
            'The storage audit confirms a durable checkpoint for marker RPT-C23.',
        ];
        $paragraph = implode(' ', array_merge(...array_fill(0, 29, $sentences)));
        $body = $paragraph . ' The final verification note carries marker TAIL-541 after the repeated report.'
            . "\n\r\n\tA second paragraph describes routine deployment checks.\r"
            . "A third paragraph records the operator's handoff.\n"
            . "See https://example.org/docs?a=1&b=2 for the checklist.\n";
        return "\t\u{00A0}\r\n" . $body . "\r\n\t\u{00A0}";
    }

    private static function abbreviationText(): string
    {
        return 'И. И. Иванов проверил отчёт, т. е. сверил данные и т. д. Затем он свернул на ул. Ленина. '
            . 'Проф. Петров встретил его там. 1. Открыть журнал. 2. Записать результат.';
    }

    private static function html(string $source): string
    {
        $body = htmlspecialchars($source, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        return '<article data-case="fixture"><p class="body">' . $body . '</p>'
            . '<p>Routine status information is recorded here.</p>'
            . '<a href="https://example.org/docs?a=1&amp;b=2">Documentation</a>'
            . '<pre><code>deploy --region "eu-west"</code></pre><script>const untouched = "script";</script>'
            . '<style>.fixture { color: #123456; }</style><!-- fixed fixture comment -->'
            . '</article>';
    }

    /** @return list<string> */
    private static function protectedBlocks(): array
    {
        return [
            '<pre><code>deploy --region "eu-west"</code></pre>',
            '<script>const untouched = "script";</script>',
            '<style>.fixture { color: #123456; }</style>',
            '<!-- fixed fixture comment -->',
        ];
    }
}
