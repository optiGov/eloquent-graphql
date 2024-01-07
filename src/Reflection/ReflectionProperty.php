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

    public function isNullable(): bool
    {
        return str_starts_with($this->type, '?');
    }

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
        $type = ltrim($this->type, '?');

        return rtrim($type, '[]');
    }

    public function isPrimitiveType(): bool
    {
        return match ($this->getType()) {
            'int', 'bool', 'boolean', 'string', 'float' => true,
            default => false
        };
    }

    public function setType(string $type): ReflectionProperty
    {
        $this->type = $type;

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

    public function isArrayType(): bool
    {
        return str_ends_with($this->type, '[]');
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
}
