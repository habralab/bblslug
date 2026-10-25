<?php

declare(strict_types=1);

namespace Bblslug\Tests\Validation;

use Bblslug\Validation\EmbeddedJsonValidator;
use PHPUnit\Framework\TestCase;

final class EmbeddedJsonValidatorTest extends TestCase
{
    /** @dataProvider changedContainers */
    public function testChangedEmbeddedContainersAreRejected(string $source, string $output): void
    {
        $validator = new EmbeddedJsonValidator(self::outer($source));
        $result = $validator->validate(self::outer($output));
        self::assertFalse($result->isValid());
        self::assertStringContainsString('$/payload', $result->getErrors()[0]);
    }

    /** @return array<string,array{string,string}> */
    public static function changedContainers(): array
    {
        return [
            'cleared string' => ['{"text":"Hello"}', '{"text":""}'],
            'syntax' => ['{"text":"Hello"}', '{"text":"broken "quote""}'],
            'container type' => ['{}', '[]'],
            'missing null' => ['{"text":"Hello","optional":null}', '{"text":"Привет"}'],
            'integer' => ['{"id":1}', '{"id":2}'],
            'boolean' => ['{"enabled":true}', '{"enabled":false}'],
            'precision' => ['{"number":0.12345678901234567891}', '{"number":0.12345678901234567892}'],
            'big integer' => ['{"id":9223372036854775808}', '{"id":9223372036854775809}'],
            'field binding' => ['{"a":1,"b":2}', '{"b":1,"a":2}'],
            'array positions' => ['[1,2]', '[2,1]'],
        ];
    }

    public function testRecursivelyEncodedChildContainersAreChecked(): void
    {
        $source = self::outer(json_encode(['child' => '[{"id":1}]'], JSON_THROW_ON_ERROR));
        $output = self::outer(json_encode(['child' => '[{"id":2}]'], JSON_THROW_ON_ERROR));
        $result = (new EmbeddedJsonValidator($source))->validate($output);
        self::assertFalse($result->isValid());
        self::assertStringContainsString('$/payload (encoded)/child', $result->getErrors()[0]);
    }

    public function testEmbeddedContainersInObjectAndArrayValuesFollowTheirSourcePaths(): void
    {
        $source = '{"items":["{\"a\":1,\"text\":\"Hello\"}","[]"],"text":"Source"}';
        $output = '{"text":"Перевод","items":["{\"text\":\"Привет\",\"a\":1}","[]"]}';
        self::assertTrue((new EmbeddedJsonValidator($source))->validate($output)->isValid());
    }

    public function testOnlySourceObjectAndArrayStringsOptInToValidation(): void
    {
        foreach (['123', 'true', 'null', '"string"', '{invalid', '[invalid', 'Ordinary text'] as $source) {
            $validator = new EmbeddedJsonValidator(self::outer($source));
            self::assertTrue($validator->validate(self::outer('Перевод'))->isValid());
        }
    }

    public function testDiagnosticEscapesPropertyNamesAndDoesNotExposeValues(): void
    {
        $source = json_encode(['a/b~c' => '{"secret":12345}'], JSON_THROW_ON_ERROR);
        $output = json_encode(['a/b~c' => '{"secret":54321}'], JSON_THROW_ON_ERROR);
        $result = (new EmbeddedJsonValidator($source))->validate($output);
        self::assertStringContainsString('$/a~1b~0c', $result->getErrors()[0]);
        self::assertStringNotContainsString('12345', $result->getErrors()[0]);
        self::assertStringNotContainsString('54321', $result->getErrors()[0]);
    }

    public function testRecursivelyEncodedStringsCannotBeCleared(): void
    {
        $source = self::outer(json_encode(['child' => '{"label":"Source"}'], JSON_THROW_ON_ERROR));
        $output = self::outer(json_encode(['child' => '{"label":""}'], JSON_THROW_ON_ERROR));
        $result = (new EmbeddedJsonValidator($source))->validate($output);
        self::assertFalse($result->isValid());
        self::assertStringContainsString('$/payload (encoded)/child (encoded)', $result->getErrors()[0]);
        self::assertStringContainsString('$/label', $result->getErrors()[0]);
    }

    private static function outer(string $payload): string
    {
        return json_encode(['payload' => $payload], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
