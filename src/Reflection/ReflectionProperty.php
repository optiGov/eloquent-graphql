<?php

namespace EloquentGraphQL\Reflection;

class ReflectionProperty
{
    const KIND_READ = 'READ';

    const KIND_WRITE = 'WRITE';

    const KIND_DEFAULT = 'DEFAULT';

    /**
     * Name of the property.
     */
    private string $name = '';

    /**
     * Type of the property.
     */
    private string $type = '';

    /**
     * Determines whether the property is nullable.
     */
    private bool $isNullable = false;

    /**
     * Determines whether the property is an array type.
     */
    private bool $isArrayType = false;

    /**
     * Kind of the property (default, read or write).
     */
    private string $kind = ReflectionProperty::KIND_DEFAULT;

    /**
     * Determines whether the property has a default value.
     */
    private bool $hasDefaultValue = false;

    /**
     * The property's default value.
     */
    private mixed $defaultValue;

    /**
     * Determines whether the property has filters.
     */
    private bool $hasFilters = false;

    /**
     * Determines whether the property has order.
     */
    private bool $hasOrder = false;

    /**
     * Determines whether the property has pagination.
     */
    private bool $hasPagination = false;

    /**
     * Determines whether the property is computed.
     */
    private bool $isComputed = false;

    /**
     * Determines whether eager loading is disabled.
     */
    private bool $eagerLoadDisabled = false;

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): ReflectionProperty
    {
        $this->name = $name;

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function isPrimitiveType(): bool
    {
        return match (strtolower($this->getType())) {
            'int', 'bool', 'boolean', 'string', 'float', 'carbon' => true,
            default => false
        };
    }

    public function setType(string $type): ReflectionProperty
    {
        $this->type = $type;

        return $this;
    }

    public function isNullable(): bool
    {
        return $this->isNullable;
    }

    public function setIsNullable(bool $isNullable): ReflectionProperty
    {
        $this->isNullable = $isNullable;

        return $this;
    }

    public function isArrayType(): bool
    {
        return $this->isArrayType;
    }

    public function setIsArrayType(bool $isArray): ReflectionProperty
    {
        $this->isArrayType = $isArray;

        return $this;
    }

    public function hasDefaultValue(): bool
    {
        return $this->hasDefaultValue;
    }

    public function setHasDefaultValue(bool $hasDefaultValue): ReflectionProperty
    {
        $this->hasDefaultValue = $hasDefaultValue;

        return $this;
    }

    public function getDefaultValue(): mixed
    {
        return $this->defaultValue;
    }

    public function setDefaultValue(mixed $defaultValue): ReflectionProperty
    {
        $this->defaultValue = $defaultValue;

        return $this;
    }

    /**
     * Sets the property kind.
     */
    public function setKind(string $kind): ReflectionProperty
    {
        $this->kind = $kind;

        return $this;
    }

    /**
     * Returns the property kind.
     */
    public function getKind(): string
    {
        return $this->kind;
    }

    /**
     * Returns whether the property is writable.
     */
    public function isWritable(): bool
    {
        return match ($this->getKind()) {
            ReflectionProperty::KIND_READ => false,
            default => true
        };
    }

    /**
     * Returns whether the property is readable.
     */
    public function isReadable(): bool
    {
        return match ($this->getKind()) {
            ReflectionProperty::KIND_WRITE => false,
            default => true
        };
    }

    /**
     * Returns whether the property has filters.
     */
    public function hasFilters(): bool
    {
        return $this->hasFilters;
    }

    /**
     * Sets whether the property has filters.
     */
    public function setHasFilters(bool $hasFilters): ReflectionProperty
    {
        $this->hasFilters = $hasFilters;

        return $this;
    }

    /**
     * Returns whether the property has order.
     */
    public function hasOrder(): bool
    {
        return $this->hasOrder;
    }

    /**
     * Sets whether the property has order.
     */
    public function setHasOrder(bool $hasOrder): ReflectionProperty
    {
        $this->hasOrder = $hasOrder;

        return $this;
    }

    /**
     * Returns whether the property has pagination.
     */
    public function hasPagination(): bool
    {
        return $this->hasPagination;
    }

    /**
     * Sets whether the property has pagination.
     */
    public function setHasPagination(bool $hasPagination): ReflectionProperty
    {
        $this->hasPagination = $hasPagination;

        return $this;
    }

    /**
     * Returns whether the property is computed.
     */
    public function isComputed(): bool
    {
        return $this->isComputed;
    }

    /**
     * Sets whether the property is computed.
     */
    public function setIsComputed(bool $isComputed): ReflectionProperty
    {
        $this->isComputed = $isComputed;

        return $this;
    }

    /**
     * Returns whether eager loading is disabled.
     */
    public function isEagerLoadDisabled(): bool
    {
        return $this->eagerLoadDisabled;
    }

    /**
     * Sets whether eager loading is disabled.
     */
    public function setEagerLoadDisabled(bool $eagerLoadDisabled): ReflectionProperty
    {
        $this->eagerLoadDisabled = $eagerLoadDisabled;

        return $this;
    }
}
