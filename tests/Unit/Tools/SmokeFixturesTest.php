<?php

declare(strict_types=1);

namespace Bblslug\Tests\Tools;

use Bblslug\Tools\Smoke\Fixtures;
use PHPUnit\Framework\TestCase;

final class SmokeFixturesTest extends TestCase
{
    public static function setUpBeforeClass(): void
    {
        require_once dirname(__DIR__, 3) . '/tools/smoke/Fixtures.php';
    }

    public function testProvidesLargeAndAbbreviationCasesForEveryFormat(): void
    {
        $cases = Fixtures::cases();

        self::assertSame(['large', 'abbreviations'], array_keys($cases));
        foreach ($cases as $fixtureName => $formats) {
            self::assertSame(['text', 'html', 'json'], array_keys($formats));
            foreach ($formats as $format => $case) {
                self::assertSame($format, $case['format']);
                self::assertSame(
                    $fixtureName === 'large' ? ['EN', 'DE', 2000] : ['RU', 'EN', 180],
                    [$case['sourceLang'], $case['targetLang'], $case['cap']]
                );
                self::assertArrayHasKey('input', $case);
                self::assertArrayHasKey('filters', $case);
                self::assertArrayHasKey('hints', $case);
            }
        }

        $large = $cases['large']['text'];
        self::assertGreaterThan($large['cap'], strlen($large['input']));
        self::assertSame(29, substr_count($large['input'], 'RPT-A17'));
        self::assertSame(29, substr_count($large['input'], 'RPT-B19'));
        self::assertSame(29, substr_count($large['input'], 'RPT-C23'));
        self::assertSame(1, substr_count($large['input'], 'TAIL-541'));
        self::assertGreaterThan(
            strrpos($large['input'], 'RPT-C23'),
            strpos($large['input'], 'TAIL-541')
        );

        $abbreviations = $cases['abbreviations']['text'];
        self::assertSame(['RU', 'EN', 180, ['ул.', 'Проф.']], [
            $abbreviations['sourceLang'],
            $abbreviations['targetLang'],
            $abbreviations['cap'],
            $abbreviations['hints'],
        ]);
        self::assertGreaterThan($abbreviations['cap'], strlen($abbreviations['input']));
    }

    public function testLargeTextCheckerAcceptsPreservedMarkersAndLineEdges(): void
    {
        $case = Fixtures::cases()['large']['text'];
        $output = str_replace(
            ['The gateway reports', 'The worker records', 'The storage audit confirms', 'The final verification note'],
            ['Das Gateway meldet', 'Der Worker protokolliert',
                'Die Speicherprüfung bestätigt', 'Der letzte Prüfvermerk'],
            $case['input']
        );

        $checks = Fixtures::check($case, $output);

        self::assertTrue($checks['allMarkersPreserved']);
        self::assertTrue($checks['tailAfterRepeatedContent']);
        self::assertTrue($checks['lineEdgesPreserved']);
    }

    public function testCheckerRejectsUntranslatedAndMissingMarkerOutputs(): void
    {
        $case = Fixtures::cases()['large']['text'];

        try {
            Fixtures::check($case, $case['input']);
            self::fail('Identical source should be rejected');
        } catch (\RuntimeException $exception) {
            self::assertSame('Empty or untranslated fixture result', $exception->getMessage());
        }

        $missingMarker = str_replace('RPT-B19', 'RPT-BXX', $case['input']);
        $translated = str_replace('The gateway reports', 'Das Gateway meldet', $missingMarker);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Large fixture marker count changed: RPT-B19');
        Fixtures::check($case, $translated);
    }

    public function testHtmlAndJsonCheckersRejectProtectedOrUnselectedMutations(): void
    {
        $cases = Fixtures::cases()['large'];
        $htmlCase = $cases['html'];
        $htmlOutput = str_replace('Routine status information', 'Routine Statusinformation', $htmlCase['input']);
        $mutatedHtml = str_replace(
            '<pre><code>deploy --region "eu-west"</code></pre>',
            '<pre><code>deploy --region eu-west</code></pre>',
            $htmlOutput
        );
        try {
            Fixtures::check($htmlCase, $mutatedHtml);
            self::fail('Changed protected code should be rejected');
        } catch (\RuntimeException $exception) {
            self::assertSame('Protected HTML block changed', $exception->getMessage());
        }

        $jsonCase = $cases['json'];
        $mutatedJson = str_replace('"decimal":1.2300', '"decimal":1.23', $jsonCase['input']);
        try {
            Fixtures::check($jsonCase, $mutatedJson);
            self::fail('Changed unselected JSON lexeme should be rejected');
        } catch (\RuntimeException $exception) {
            self::assertSame('Unselected JSON lexical content changed', $exception->getMessage());
        }
    }

    public function testCheckerRejectsNormalizedLineSeparators(): void
    {
        $case = Fixtures::cases()['large']['text'];
        $output = str_replace(
            ['The gateway reports', "\r\n", "\r"],
            ['Das Gateway meldet', "\n", "\n"],
            $case['input']
        );
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Text fixture line separators changed');
        Fixtures::check($case, $output);
    }

    public function testCheckerRejectsChangedHtmlAttributes(): void
    {
        $case = Fixtures::cases()['large']['html'];
        $output = str_replace('class="body"', 'class="changed"', $case['input']);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('HTML tag bytes or order changed');
        Fixtures::check($case, $output);
    }

    public function testValidJsonWithDroppedRepeatedContentIsRejected(): void
    {
        $case = Fixtures::cases()['large']['json'];
        $output = str_replace('RPT-C23', 'RPT-C99', $case['input']);
        json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Large fixture marker count changed: RPT-C23');
        Fixtures::check($case, $output);
    }

    public function testAbbreviationCheckerUsesReviewedNamesAndStreetOrder(): void
    {
        $case = Fixtures::cases()['abbreviations']['text'];
        $sentence = 'I. I. Ivanov checked the report. He turned onto Lenin Street. Prof. Petrov met him.';
        $output = implode(' ', array_fill(0, 2, $sentence));

        $checks = Fixtures::check($case, $output);

        self::assertTrue($checks['reviewedAbbreviationFactsPreserved']);
        $badOrder = str_replace('Lenin Street', 'Street of Lenin', $output);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Abbreviation fixture street reference count changed');
        Fixtures::check($case, $badOrder);
    }
}
