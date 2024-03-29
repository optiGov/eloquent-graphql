<?php

namespace EloquentGraphQL\Reflection;

use Illuminate\Support\Collection;
use ReflectionException;

class ReflectionInspector
{
    /**
     * Cache of all inspections run.
     *
     * @var array|array[]
     */
    private static array $cache = [
        'classDoc' => [],
        'constructor' => [],
    ];

    /**
     * @throws ReflectionException
     */
    public static function getShortClassName(string $class): string
    {
        return (new \ReflectionClass($class))
            ->getShortName();
    }

    /**
     * @throws ReflectionException
     */
    public static function getPropertiesFromClassDoc(string $class): Collection
    {
        // check if class is cached
        if (array_key_exists($class, self::$cache['classDoc'])) {
            return self::$cache['classDoc'][$class];
        }

        // obtain parameters and properties
        $reflection = new \ReflectionClass($class);

        $doc = $reflection->getDocComment();
        if ($doc === false) {
            return new Collection();
        }

        $properties = static::parsePropertiesFromClassDoc($doc);

        return static::fullQualifyProperties($properties, $reflection->getNamespaceName());
    }

    /**
     * Takes parsed properties and adds the full qualified name.
     */
    private static function fullQualifyProperties(Collection $properties, string $namespace): Collection
    {
        $properties
            ->filter(fn ($property) => ! $property->isPrimitiveType())
            ->filter(fn ($property) => ! str_contains($property->getType(), '\\'))
            ->each(function (ReflectionProperty $property) use ($namespace) {
                $prefix = $property->isNullable() ? '?' : '';
                $postfix = $property->isArrayType() ? '[]' : '';
                $property->setType($prefix.$namespace.'\\'.$property->getType().$postfix);
            });

        return $properties;
    }

    /**
     * Parses a doc string and returns an AST for the properties.
     */
    private static function parsePropertiesFromClassDoc(string $doc): Collection
    {
        $lines = explode("\n", $doc);

        $textProperties = new Collection();

        // match properties
        foreach ($lines as $line) {
            $regex = '/ ?\*? ?@property(-read|-write)? (\??(\\\\?([A-Z]|[a-z]|[0-9]|_)+)+(\[\])?) (\$([A-Z]|[a-z]|[0-9]|_)+) ?(@paginate)? ?(@filterable)? ?(@orderable)? ?(@computed)?/m';

            preg_match_all($regex, $line, $matches, PREG_PATTERN_ORDER, 0);

            if (! empty($matches[0])) {
                $kind = match ($matches[1][0]) {
                    '-read' => ReflectionProperty::KIND_READ,
                    '-write' => ReflectionProperty::KIND_WRITE,
                    default => ReflectionProperty::KIND_DEFAULT
                };

                $textProperties->add(
                    (new ReflectionProperty())
                        ->setName(substr($matches[6][0], 1))
                        ->setType($matches[2][0])
                        ->setKind($kind)
                        ->setHasDefaultValue(false)
                        ->setHasPagination($matches[8][0] === '@paginate')
                        ->setHasFilters($matches[9][0] === '@filterable')
                        ->setHasOrder($matches[10][0] === '@orderable')
                        ->setIsComputed($matches[11][0] === '@computed')
                );
            }
        }

        return $textProperties;
    }
}
