<?php

namespace EloquentGraphQL\Tests\Reflection;

use EloquentGraphQL\Reflection\ReflectionInspector;
use EloquentGraphQL\Reflection\ReflectionProperty;
use EloquentGraphQL\Tests\Models\Author;
use EloquentGraphQL\Tests\Models\Book;
use PHPUnit\Framework\TestCase;

class ReflectionInspectorTest extends TestCase
{
    /**
     * The cache is a static property — reset it between tests so they don't
     * bleed into each other.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $cacheProperty = new \ReflectionProperty(ReflectionInspector::class, 'cache');
        $cacheProperty->setValue(null, ['classDoc' => [], 'constructor' => []]);
    }

    public function testParsesPropertiesFromDocblock(): void
    {
        $properties = ReflectionInspector::getPropertiesFromClassDoc(Author::class);

        $this->assertNotEmpty($properties);

        $names = $properties->map(fn (ReflectionProperty $p) => $p->getName())->toArray();
        $this->assertContains('id', $names);
        $this->assertContains('name', $names);
        $this->assertContains('email', $names);
        $this->assertContains('books', $names);
        $this->assertContains('publisher', $names);
    }

    public function testPrimitiveTypesAreDetectedCorrectly(): void
    {
        $properties = ReflectionInspector::getPropertiesFromClassDoc(Author::class)
            ->keyBy(fn (ReflectionProperty $p) => $p->getName());

        $this->assertTrue($properties['id']->isPrimitiveType());
        $this->assertTrue($properties['name']->isPrimitiveType());
        $this->assertFalse($properties['publisher']->isPrimitiveType());
    }

    public function testArrayTypeIsDetectedForHasManyRelation(): void
    {
        $properties = ReflectionInspector::getPropertiesFromClassDoc(Author::class)
            ->keyBy(fn (ReflectionProperty $p) => $p->getName());

        $this->assertTrue($properties['books']->isArrayType());
        $this->assertFalse($properties['publisher']->isArrayType());
    }

    public function testAnnotationsAreParsed(): void
    {
        $properties = ReflectionInspector::getPropertiesFromClassDoc(Author::class)
            ->keyBy(fn (ReflectionProperty $p) => $p->getName());

        // @paginate and @filter are declared on the books property
        $this->assertTrue($properties['books']->hasPagination());
        $this->assertTrue($properties['books']->hasFilters());
        $this->assertFalse($properties['books']->hasOrder());
    }

    public function testResultIsCached(): void
    {
        // First call populates the cache.
        $first = ReflectionInspector::getPropertiesFromClassDoc(Author::class);

        // Second call must return the identical Collection instance (same object).
        $second = ReflectionInspector::getPropertiesFromClassDoc(Author::class);

        $this->assertSame($first, $second, 'Expected the cached Collection to be returned on the second call.');
    }

    public function testReturnsEmptyCollectionForClassWithoutDocblock(): void
    {
        // Use a dynamically created class that has no docblock comment.
        $class = get_class(new class extends \stdClass {
        });

        $properties = ReflectionInspector::getPropertiesFromClassDoc($class);

        $this->assertTrue($properties->isEmpty());
    }

    public function testEmptyCollectionIsCachedForClassWithoutDocblock(): void
    {
        $class = get_class(new class extends \stdClass {
        });

        $first = ReflectionInspector::getPropertiesFromClassDoc($class);
        $second = ReflectionInspector::getPropertiesFromClassDoc($class);

        $this->assertSame($first, $second);
    }

    public function testTypeIsFullyQualifiedForRelativeClass(): void
    {
        $properties = ReflectionInspector::getPropertiesFromClassDoc(Author::class)
            ->keyBy(fn (ReflectionProperty $p) => $p->getName());

        // 'Publisher' in the docblock should be resolved to its fully-qualified name.
        $this->assertStringContainsString('\\', $properties['publisher']->getType());
    }

    public function testNullablePropertyIsDetected(): void
    {
        $properties = ReflectionInspector::getPropertiesFromClassDoc(Book::class)
            ->keyBy(fn (ReflectionProperty $p) => $p->getName());

        // 'id' is declared as plain int (not nullable).
        $this->assertFalse($properties['id']->isNullable());
    }
}
