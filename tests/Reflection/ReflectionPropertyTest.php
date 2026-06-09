<?php

namespace EloquentGraphQL\Tests\Reflection;

use EloquentGraphQL\Reflection\ReflectionProperty;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for ReflectionProperty — the value object that represents a
 * single @property entry parsed from a model's docblock.
 */
class ReflectionPropertyTest extends TestCase
{
    private function make(string $kind = ReflectionProperty::KIND_DEFAULT): ReflectionProperty
    {
        return (new ReflectionProperty())
            ->setName('field')
            ->setType('string')
            ->setKind($kind)
            ->setIsNullable(false)
            ->setIsArrayType(false)
            ->setHasDefaultValue(false);
    }

    // -----------------------------------------------------------------------
    // isReadable / isWritable
    // -----------------------------------------------------------------------

    public function testDefaultKindIsReadableAndWritable(): void
    {
        $p = $this->make(ReflectionProperty::KIND_DEFAULT);

        $this->assertTrue($p->isReadable());
        $this->assertTrue($p->isWritable());
    }

    public function testReadKindIsReadableButNotWritable(): void
    {
        $p = $this->make(ReflectionProperty::KIND_READ);

        $this->assertTrue($p->isReadable());
        $this->assertFalse($p->isWritable());
    }

    public function testWriteKindIsWritableButNotReadable(): void
    {
        $p = $this->make(ReflectionProperty::KIND_WRITE);

        $this->assertFalse($p->isReadable());
        $this->assertTrue($p->isWritable());
    }

    // -----------------------------------------------------------------------
    // isPrimitiveType
    // -----------------------------------------------------------------------

    /** @dataProvider primitiveTypeProvider */
    public function testPrimitiveTypesAreRecognised(string $type): void
    {
        $p = $this->make()->setType($type);
        $this->assertTrue($p->isPrimitiveType(), "'$type' should be a primitive type.");
    }

    public static function primitiveTypeProvider(): array
    {
        return [
            'int'     => ['int'],
            'bool'    => ['bool'],
            'boolean' => ['boolean'],
            'string'  => ['string'],
            'float'   => ['float'],
            'carbon'  => ['carbon'],
            // Case-insensitive
            'Int'     => ['Int'],
            'STRING'  => ['STRING'],
        ];
    }

    /** @dataProvider nonPrimitiveTypeProvider */
    public function testNonPrimitiveTypesAreNotPrimitive(string $type): void
    {
        $p = $this->make()->setType($type);
        $this->assertFalse($p->isPrimitiveType(), "'$type' should NOT be a primitive type.");
    }

    public static function nonPrimitiveTypeProvider(): array
    {
        return [
            'App\\Models\\Book'    => ['App\\Models\\Book'],
            'DateTime'            => ['DateTime'],
            'array'               => ['array'],
        ];
    }

    // -----------------------------------------------------------------------
    // isNullable / isArrayType
    // -----------------------------------------------------------------------

    public function testNullableFlag(): void
    {
        $p = $this->make()->setIsNullable(true);
        $this->assertTrue($p->isNullable());

        $p->setIsNullable(false);
        $this->assertFalse($p->isNullable());
    }

    public function testArrayTypeFlag(): void
    {
        $p = $this->make()->setIsArrayType(true);
        $this->assertTrue($p->isArrayType());

        $p->setIsArrayType(false);
        $this->assertFalse($p->isArrayType());
    }

    // -----------------------------------------------------------------------
    // hasFilters / hasOrder / hasPagination / isComputed / isEagerLoadDisabled
    // -----------------------------------------------------------------------

    public function testAnnotationFlagsDefaultToFalse(): void
    {
        $p = $this->make();

        $this->assertFalse($p->hasFilters());
        $this->assertFalse($p->hasOrder());
        $this->assertFalse($p->hasPagination());
        $this->assertFalse($p->isComputed());
        $this->assertFalse($p->isEagerLoadDisabled());
    }

    public function testAnnotationFlagsCanBeEnabled(): void
    {
        $p = $this->make()
            ->setHasFilters(true)
            ->setHasOrder(true)
            ->setHasPagination(true)
            ->setIsComputed(true)
            ->setEagerLoadDisabled(true);

        $this->assertTrue($p->hasFilters());
        $this->assertTrue($p->hasOrder());
        $this->assertTrue($p->hasPagination());
        $this->assertTrue($p->isComputed());
        $this->assertTrue($p->isEagerLoadDisabled());
    }

    // -----------------------------------------------------------------------
    // defaultValue
    // -----------------------------------------------------------------------

    public function testDefaultValueIsReturnedWhenSet(): void
    {
        $p = $this->make()
            ->setHasDefaultValue(true)
            ->setDefaultValue('hello');

        $this->assertTrue($p->hasDefaultValue());
        $this->assertSame('hello', $p->getDefaultValue());
    }

    public function testDefaultValueFlagIsFalseByDefault(): void
    {
        $p = $this->make();
        $this->assertFalse($p->hasDefaultValue());
    }

    // -----------------------------------------------------------------------
    // Fluent builder — setters return $this
    // -----------------------------------------------------------------------

    public function testSettersReturnSelf(): void
    {
        $p = new ReflectionProperty();

        $this->assertSame($p, $p->setName('x'));
        $this->assertSame($p, $p->setType('string'));
        $this->assertSame($p, $p->setKind(ReflectionProperty::KIND_DEFAULT));
        $this->assertSame($p, $p->setIsNullable(false));
        $this->assertSame($p, $p->setIsArrayType(false));
        $this->assertSame($p, $p->setHasDefaultValue(false));
        $this->assertSame($p, $p->setHasFilters(false));
        $this->assertSame($p, $p->setHasOrder(false));
        $this->assertSame($p, $p->setHasPagination(false));
        $this->assertSame($p, $p->setIsComputed(false));
        $this->assertSame($p, $p->setEagerLoadDisabled(false));
    }
}
