<?php

namespace EloquentGraphQL\Tests\Types;

use EloquentGraphQL\Exceptions\EloquentGraphQLException;
use EloquentGraphQL\Factories\TypeFactory\Field\TypeFieldFactoryFilter;
use EloquentGraphQL\Factories\TypeFactory\Field\TypeFieldFactoryScalar;
use EloquentGraphQL\Reflection\ReflectionProperty;
use EloquentGraphQL\Services\EloquentGraphQLService;
use EloquentGraphQL\Tests\Models\Author;
use PHPUnit\Framework\TestCase;

/**
 * Verifies that TypeFieldFactory subclasses throw a meaningful
 * EloquentGraphQLException instead of an UnhandledMatchError when an
 * unsupported property type is encountered.
 */
class TypeFieldExceptionTest extends TestCase
{
    private function makeProperty(string $type, string $name = 'someField'): ReflectionProperty
    {
        return (new ReflectionProperty())
            ->setName($name)
            ->setType($type)
            ->setIsNullable(false)
            ->setIsArrayType(false)
            ->setKind(ReflectionProperty::KIND_DEFAULT)
            ->setHasDefaultValue(false);
    }

    public function testScalarFactoryThrowsForUnknownType(): void
    {
        $this->expectException(EloquentGraphQLException::class);
        $this->expectExceptionMessageMatches('/Unsupported scalar type/');

        (new TypeFieldFactoryScalar(new EloquentGraphQLService()))
            ->setFieldName('someField')
            ->setProperty($this->makeProperty('DateTime'))
            ->setModel(Author::class)
            ->build();
    }

    public function testFilterFactoryThrowsForUnknownType(): void
    {
        $this->expectException(EloquentGraphQLException::class);
        $this->expectExceptionMessageMatches('/Unsupported filter type/');

        (new TypeFieldFactoryFilter(new EloquentGraphQLService()))
            ->setFieldName('someField')
            ->setProperty($this->makeProperty('DateTime'))
            ->setModel(Author::class)
            ->build();
    }

    public function testScalarFactoryThrowsForLiteralArrayType(): void
    {
        $this->expectException(EloquentGraphQLException::class);
        $this->expectExceptionMessageMatches('/literal type .array./');

        (new TypeFieldFactoryScalar(new EloquentGraphQLService()))
            ->setFieldName('someField')
            ->setProperty($this->makeProperty('array'))
            ->setModel(Author::class)
            ->build();
    }

    public function testFilterFactoryThrowsForArrayType(): void
    {
        $this->expectException(EloquentGraphQLException::class);

        (new TypeFieldFactoryFilter(new EloquentGraphQLService()))
            ->setFieldName('someField')
            ->setProperty($this->makeProperty('array'))
            ->setModel(Author::class)
            ->build();
    }

    /**
     * Verify all officially supported scalar types resolve without exception.
     *
     * @dataProvider supportedScalarTypes
     */
    public function testScalarFactoryAcceptsSupportedTypes(string $type): void
    {
        $this->expectNotToPerformAssertions();

        (new TypeFieldFactoryScalar(new EloquentGraphQLService()))
            ->setFieldName('someField')
            ->setProperty($this->makeProperty($type))
            ->setModel(Author::class)
            ->build();
    }

    public static function supportedScalarTypes(): array
    {
        return [
            ['string'],
            ['int'],
            ['float'],
            ['bool'],
            ['boolean'],
            ['carbon'],
        ];
    }
}
