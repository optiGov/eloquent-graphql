<?php

namespace EloquentGraphQL\Tests\Types;

use EloquentGraphQL\Factories\TypeFactory\Field\TypeFieldFactoryFilter;
use EloquentGraphQL\Factories\TypeFactory\Field\TypeFieldFactoryScalar;
use EloquentGraphQL\Language\VocabularyEnglish;
use EloquentGraphQL\Reflection\ReflectionProperty;
use EloquentGraphQL\Security\SecurityGuard;
use EloquentGraphQL\Services\EloquentGraphQLService;
use EloquentGraphQL\Tests\Enums\Priority;
use EloquentGraphQL\Tests\Enums\Status;
use EloquentGraphQL\Tests\Models\Article;
use GraphQL\Type\Definition\EnumType;
use GraphQL\Type\Definition\InputObjectType;
use GraphQL\Type\Definition\ListOfType;
use GraphQL\Type\Definition\NonNull;
use PHPUnit\Framework\TestCase;

/**
 * Tests for automatic GraphQL EnumType generation from PHP 8.1+ enum properties.
 */
class EnumTypeTest extends TestCase
{
    /**
     * Returns an EloquentGraphQLService whose SecurityGuard allows every action.
     * Required for tests that invoke resolve closures, because the real guard
     * needs a Laravel IoC container (Gate) which is not available in unit tests.
     */
    private function makeService(): EloquentGraphQLService
    {
        return new class extends EloquentGraphQLService {
            public function security(): SecurityGuard
            {
                // Anonymous sub-class that overrides every assert* method to be a no-op.
                static $guard = null;
                if ($guard === null) {
                    $outer = $this;
                    $guard = new class($outer, new VocabularyEnglish()) extends SecurityGuard {
                        public function assertCanViewProperty(object $model, ReflectionProperty $property): void {}
                    };
                }

                return $guard;
            }
        };
    }
    // -----------------------------------------------------------------------
    // ReflectionProperty::isEnumType()
    // -----------------------------------------------------------------------

    public function testIsEnumTypeReturnsTrueForBackedStringEnum(): void
    {
        $property = (new ReflectionProperty())
            ->setName('status')
            ->setType(Status::class);

        $this->assertTrue($property->isEnumType());
    }

    public function testIsEnumTypeReturnsTrueForBackedIntEnum(): void
    {
        $property = (new ReflectionProperty())
            ->setName('priority')
            ->setType(Priority::class);

        $this->assertTrue($property->isEnumType());
    }

    public function testIsEnumTypeReturnsFalseForPrimitiveString(): void
    {
        $property = (new ReflectionProperty())
            ->setName('title')
            ->setType('string');

        $this->assertFalse($property->isEnumType());
    }

    public function testIsEnumTypeReturnsFalseForNonExistentClass(): void
    {
        $property = (new ReflectionProperty())
            ->setName('field')
            ->setType('NonExistentClass\That\DoesNotExist');

        $this->assertFalse($property->isEnumType());
    }

    public function testIsEnumTypeReturnsFalseForRegularClass(): void
    {
        $property = (new ReflectionProperty())
            ->setName('field')
            ->setType(Article::class);

        $this->assertFalse($property->isEnumType());
    }

    // -----------------------------------------------------------------------
    // EloquentGraphQLService::enumType()
    // -----------------------------------------------------------------------

    public function testEnumTypeBuildsCorrectGraphQLEnumType(): void
    {
        $service  = new EloquentGraphQLService();
        $enumType = $service->enumType(Status::class);

        $this->assertInstanceOf(EnumType::class, $enumType);
        $this->assertSame('Status', $enumType->name);
    }

    public function testEnumTypeIncludesAllCases(): void
    {
        $service  = new EloquentGraphQLService();
        $enumType = $service->enumType(Status::class);

        $values = $enumType->getValues();
        $names  = array_column($values, 'name');

        $this->assertContains('Active', $names);
        $this->assertContains('Inactive', $names);
        $this->assertContains('Pending', $names);
        $this->assertCount(3, $values);
    }

    public function testEnumTypeInternalValueIsBackedValue(): void
    {
        $service  = new EloquentGraphQLService();
        $enumType = $service->enumType(Status::class);

        // The internal value must be the backing value so that SQL comparisons work.
        $activeValue = $enumType->getValue('Active');
        $this->assertSame('active', $activeValue->value);
    }

    public function testEnumTypeInternalValueForIntBackedEnum(): void
    {
        $service  = new EloquentGraphQLService();
        $enumType = $service->enumType(Priority::class);

        $highValue = $enumType->getValue('High');
        $this->assertSame(3, $highValue->value);
    }

    public function testEnumTypeIsCached(): void
    {
        $service = new EloquentGraphQLService();

        $first  = $service->enumType(Status::class);
        $second = $service->enumType(Status::class);

        $this->assertSame($first, $second, 'enumType() must return the same instance on repeated calls.');
    }

    // -----------------------------------------------------------------------
    // EloquentGraphQLService::enumFilterType()
    // -----------------------------------------------------------------------

    public function testEnumFilterTypeBuildsInputObjectType(): void
    {
        $service    = new EloquentGraphQLService();
        $filterType = $service->enumFilterType(Status::class);

        $this->assertInstanceOf(InputObjectType::class, $filterType);
        $this->assertSame('StatusFilterInput', $filterType->name);
    }

    public function testEnumFilterTypeHasEqNeInNinFields(): void
    {
        $service    = new EloquentGraphQLService();
        $filterType = $service->enumFilterType(Status::class);

        $this->assertNotNull($filterType->getField('eq'));
        $this->assertNotNull($filterType->getField('ne'));
        $this->assertNotNull($filterType->getField('in'));
        $this->assertNotNull($filterType->getField('nin'));
    }

    public function testEnumFilterTypeEqFieldUsesEnumType(): void
    {
        $service    = new EloquentGraphQLService();
        $filterType = $service->enumFilterType(Status::class);

        // 'eq' must be nullable enum (not wrapped in NonNull)
        $eqType = $filterType->getField('eq')->getType();
        $this->assertInstanceOf(EnumType::class, $eqType);
    }

    public function testEnumFilterTypeInFieldUsesListOfEnum(): void
    {
        $service    = new EloquentGraphQLService();
        $filterType = $service->enumFilterType(Status::class);

        // in: [Status!]
        $inType = $filterType->getField('in')->getType();
        $this->assertInstanceOf(ListOfType::class, $inType);
        // inner element must be NonNull<EnumType>
        $innerNonNull = $inType->getWrappedType();
        $this->assertInstanceOf(NonNull::class, $innerNonNull);
        $this->assertInstanceOf(EnumType::class, $innerNonNull->getWrappedType());
    }

    public function testEnumFilterTypeIsCached(): void
    {
        $service = new EloquentGraphQLService();

        $first  = $service->enumFilterType(Status::class);
        $second = $service->enumFilterType(Status::class);

        $this->assertSame($first, $second, 'enumFilterType() must return the same instance on repeated calls.');
    }

    // -----------------------------------------------------------------------
    // TypeFieldFactoryScalar — GraphQL output type for enum properties
    // -----------------------------------------------------------------------

    private function makeEnumProperty(
        string $enumClass,
        string $name = 'status',
        bool $nullable = false,
        bool $array = false,
    ): ReflectionProperty {
        return (new ReflectionProperty())
            ->setName($name)
            ->setType($enumClass)
            ->setIsNullable($nullable)
            ->setIsArrayType($array)
            ->setKind(ReflectionProperty::KIND_DEFAULT)
            ->setHasDefaultValue(false);
    }

    public function testScalarFactoryBuildsNonNullEnumTypeForRequiredProperty(): void
    {
        $field = (new TypeFieldFactoryScalar($this->makeService()))
            ->setFieldName('status')
            ->setProperty($this->makeEnumProperty(Status::class, name: 'status', nullable: false))
            ->setModel(Article::class)
            ->build();

        // Expected: Status!
        $this->assertSame('Status!', $field['type']->toString());
        $this->assertInstanceOf(EnumType::class, $field['type']->getWrappedType());
        $this->assertSame('Status', $field['type']->getWrappedType()->name);
    }

    public function testScalarFactoryBuildsNullableEnumTypeForOptionalProperty(): void
    {
        $field = (new TypeFieldFactoryScalar($this->makeService()))
            ->setFieldName('priority')
            ->setProperty($this->makeEnumProperty(Priority::class, name: 'priority', nullable: true))
            ->setModel(Article::class)
            ->build();

        // Expected: Priority  (no NonNull wrapper)
        $this->assertSame('Priority', $field['type']->toString());
        $this->assertInstanceOf(EnumType::class, $field['type']);
    }

    public function testScalarFactoryBuildsListTypeForEnumArrayProperty(): void
    {
        $field = (new TypeFieldFactoryScalar($this->makeService()))
            ->setFieldName('statuses')
            ->setProperty($this->makeEnumProperty(Status::class, name: 'statuses', nullable: false, array: true))
            ->setModel(Article::class)
            ->build();

        // Expected: [Status!]!
        $this->assertSame('[Status!]!', $field['type']->toString());
        $this->assertInstanceOf(ListOfType::class, $field['type']->getWrappedType());
    }

    // -----------------------------------------------------------------------
    // TypeFieldFactoryScalar — resolver serialises enum instances to scalar
    // -----------------------------------------------------------------------

    public function testResolverSerializesBackedEnumToItsValue(): void
    {
        $field = (new TypeFieldFactoryScalar($this->makeService()))
            ->setFieldName('status')
            ->setProperty($this->makeEnumProperty(Status::class, nullable: true))
            ->setModel(Article::class)
            ->build();

        $parent         = new \stdClass();
        $parent->status = Status::Active;

        $this->assertSame(
            'active',
            ($field['resolve'])($parent),
            'Resolver must return the backing value, not the enum instance.'
        );
    }

    public function testResolverSerializesIntBackedEnumToItsValue(): void
    {
        $field = (new TypeFieldFactoryScalar($this->makeService()))
            ->setFieldName('priority')
            ->setProperty($this->makeEnumProperty(Priority::class, name: 'priority', nullable: true))
            ->setModel(Article::class)
            ->build();

        $parent           = new \stdClass();
        $parent->priority = Priority::High;

        $this->assertSame(3, ($field['resolve'])($parent));
    }

    public function testResolverReturnsNullForNullEnumValue(): void
    {
        $field = (new TypeFieldFactoryScalar($this->makeService()))
            ->setFieldName('status')
            ->setProperty($this->makeEnumProperty(Status::class, nullable: true))
            ->setModel(Article::class)
            ->build();

        $parent         = new \stdClass();
        $parent->status = null;

        $this->assertNull(($field['resolve'])($parent));
    }

    // -----------------------------------------------------------------------
    // TypeFieldFactoryFilter — InputObjectType for enum filter
    // -----------------------------------------------------------------------

    public function testFilterFactoryBuildsEnumFilterInputForEnumProperty(): void
    {
        $field = (new TypeFieldFactoryFilter($this->makeService()))
            ->setFieldName('status')
            ->setProperty($this->makeEnumProperty(Status::class))
            ->setModel(Article::class)
            ->build();

        $this->assertInstanceOf(InputObjectType::class, $field['type']);
        $this->assertSame('StatusFilterInput', $field['type']->name);
    }

    // -----------------------------------------------------------------------
    // Full TypeFactory integration: Article model with enum properties
    // -----------------------------------------------------------------------

    public function testTypeFactoryBuildsObjectTypeWithEnumFields(): void
    {
        $service     = new EloquentGraphQLService();
        $articleType = $service->typeFactory(Article::class)->build();

        // 'status' must be Status!
        $this->assertSame('Status!', $articleType->getField('status')->getType()->toString());
        // 'priority' must be Priority (nullable, no NonNull wrapper)
        $this->assertSame('Priority', $articleType->getField('priority')->getType()->toString());
    }

    public function testTypeFactorySharedEnumTypeAcrossFields(): void
    {
        // Two fields using the same enum class must share one GraphQL type instance.
        $service     = new EloquentGraphQLService();
        $articleType = $service->typeFactory(Article::class)->build();

        // Unwrap NonNull → EnumType for 'status'
        $statusEnumType = $articleType->getField('status')->getType()->getWrappedType();
        $directEnumType = $service->enumType(Status::class);

        $this->assertSame($directEnumType, $statusEnumType);
    }
}
