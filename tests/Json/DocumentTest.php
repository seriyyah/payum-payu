<?php

declare(strict_types=1);

namespace PayumPayU\Tests\Json;

use PayumPayU\Json\Document;
use PayumPayU\Json\Exception\DocumentException;
use PayumPayU\Tests\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;

final class DocumentTest extends TestCase
{
    public function testReadsTypedValues(): void
    {
        $document = Document::fromJson('{"name":"PayU","amount":"2100","count":3,"flag":true,"nested":{"key":"value"},"items":[{"id":"a"},{"id":"b"}]}');

        self::assertTrue($document->has('name'));
        self::assertFalse($document->has('missing'));
        self::assertSame('PayU', $document->string('name'));
        self::assertSame(2100, $document->int('amount'));
        self::assertSame(3, $document->int('count'));
        self::assertTrue($document->bool('flag'));
        self::assertSame('value', $document->document('nested')->string('key'));
        self::assertSame(['a', 'b'], array_map(static fn (Document $item): string => $item->string('id'), $document->documents('items')));
        self::assertSame(['key' => 'value'], $document->document('nested')->toArray());
    }

    public function testOptionalAccessorsReturnNullForMissingOrNullValues(): void
    {
        $document = Document::fromArray(['nullable' => null, 'name' => 'x', 'amount' => 5, 'nested' => ['a' => 1]]);

        self::assertNull($document->optionalString('nullable'));
        self::assertNull($document->optionalString('missing'));
        self::assertNull($document->optionalInt('nullable'));
        self::assertNull($document->optionalInt('missing'));
        self::assertNull($document->optionalDocument('nullable'));
        self::assertNull($document->optionalDocument('missing'));
        self::assertSame('x', $document->optionalString('name'));
        self::assertSame(5, $document->optionalInt('amount'));
        self::assertSame(['a' => 1], $document->optionalDocument('nested')?->toArray());
    }

    public function testEmptyObjectIsADocument(): void
    {
        self::assertSame([], Document::fromJson('{}')->toArray());
        self::assertSame([], Document::fromJson('{"empty":{}}')->document('empty')->toArray());
    }

    public function testMalformedJsonIsRejected(): void
    {
        self::assertThrows(
            DocumentException::class,
            static fn (): Document => Document::fromJson('{not json'),
            'The JSON document is malformed',
        );
    }

    #[DataProvider('nonObjects')]
    public function testNonObjectRootIsRejected(string $json): void
    {
        self::assertThrows(
            DocumentException::class,
            static fn (): Document => Document::fromJson($json),
            'Expected a JSON object at "$"',
        );
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function nonObjects(): iterable
    {
        yield 'list' => ['[1,2]'];
        yield 'scalar' => ['"text"'];
        yield 'null' => ['null'];
    }

    public function testMissingFieldNamesItsPath(): void
    {
        self::assertThrows(
            DocumentException::class,
            static fn (): string => Document::fromArray(['order' => []])->document('order')->string('status'),
            'The field "$.order.status" is missing.',
        );
    }

    /**
     * @param \Closure(Document): mixed $read
     */
    #[DataProvider('wrongTypes')]
    public function testWrongTypesAreRejected(\Closure $read, mixed $value, string $expected): void
    {
        $document = Document::fromArray(['field' => $value]);

        self::assertThrows(
            DocumentException::class,
            static fn () => $read($document),
            sprintf('The field "$.field" must be %s.', $expected),
        );
    }

    /**
     * @return iterable<string, array{\Closure(Document): mixed, mixed, string}>
     */
    public static function wrongTypes(): iterable
    {
        $string = static fn (Document $document): string => $document->string('field');
        $int = static fn (Document $document): int => $document->int('field');
        $bool = static fn (Document $document): bool => $document->bool('field');
        $documents = static fn (Document $document): array => $document->documents('field');

        yield 'string from int' => [$string, 1, 'a string'];
        yield 'int from text' => [$int, 'abc', 'an integer'];
        yield 'int from float string' => [$int, '1.5', 'an integer'];
        yield 'int from bool' => [$int, true, 'an integer'];
        yield 'bool from string' => [$bool, 'true', 'a boolean'];
        yield 'list from object' => [$documents, ['a' => 1], 'a list'];
        yield 'list from scalar' => [$documents, 'x', 'a list'];
    }

    public function testNestedNonObjectIsRejectedWithPath(): void
    {
        self::assertThrows(
            DocumentException::class,
            static fn (): array => Document::fromArray(['items' => [['ok' => true], 'nope']])->documents('items'),
            'Expected a JSON object at "$.items[1]"',
        );
    }

    public function testNegativeNumericStringsAreIntegers(): void
    {
        self::assertSame(-5, Document::fromArray(['n' => '-5'])->int('n'));
    }
}
