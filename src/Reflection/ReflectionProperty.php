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
     * Determines wether the property has a default value.
     */
    private bool $hasDefaultValue = false;

    /**
     * The property's default value.
     */
    private mixed $defaultValue;

    /**
     * Scopes that protect the property.
     */
    private array $scopes = [];

    /**
     * Filters to be applied when a property is read.
     */
    private array $filters = [];

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
        if (str_starts_with($this->getType(), '?')) {
            $this->type = "?$type";
        } else {
            $this->type = "$type";
        }

        if (str_ends_with($this->getType(), '[]')) {
            $this->setIsArrayType(true);
        }

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
     * @return ReflectionProperty
     */
    public function setIsArrayType(bool $isArrayType): static
    {
        $this->isArrayType = $isArrayType;

        return $this;
    }

    /**
     * Adds a scope that protects the property.
     *
     * @return $this
     */
    public function addScope(string|array $scopes): static
    {
        if (is_string($scopes)) {
            $scopes = [$scopes];
        }

        foreach ($scopes as $scope) {
            $this->scopes[] = $scope;
        }

        return $this;
    }

    /**
     * Adds read filters to be applied to the property data.
     *
     * @return $this
     */
    public function addFilter(string|array $filters): static
    {
        if (is_string($filters)) {
            $filters = [$filters];
        }

        foreach ($filters as $filter) {
            $this->filters[] = $filter;
        }

        return $this;
    }

    /**
     * Returns all scopes for the property.
     */
    public function getScopes(): array
    {
        return $this->scopes;
    }

    /**
     * Returns all read filters for the property.
     */
    public function getFilters(): array
    {
        return $this->filters;
    }

    /**
     * Returns if the property has read-filters.
     */
    public function hasFilters(): bool
    {
        return empty($this->getFilters());
    }

    /**
     * Returns whether the property is protected by scopes.
     */
    public function protectedByScopes(): bool
    {
        return ! empty($this->scopes);
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
     * Returns wether the property is writable.
     */
    public function isWritable(): bool
    {
        return match ($this->getKind()) {
            ReflectionProperty::KIND_READ => false,
            default => true
        };
    }

    /**
     * Returns wether the property is readable.
     */
    public function isReadable(): bool
    {
        return match ($this->getKind()) {
            ReflectionProperty::KIND_WRITE => false,
            default => true
        };
    }
}
