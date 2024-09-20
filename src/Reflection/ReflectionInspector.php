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
                $property->setType($namespace.'\\'.$property->getType());
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
            $regex = '/ ?\*? ?@property(-read|-write)? (Collection<)?(\??(\\\\?([A-Z]|[a-z]|[0-9]|_)+)+(\[\])?)>? (\$([A-Z]|[a-z]|[0-9]|_)+) ?(@paginate)? ?(@filterable)? ?(@orderable)? ?(@computed)? ?(@eager-load-disabled)?/m';

            preg_match_all($regex, $line, $matches, PREG_PATTERN_ORDER, 0);

            if (! empty($matches[0])) {
                $kind = match ($matches[1][0]) {
                    '-read' => ReflectionProperty::KIND_READ,
                    '-write' => ReflectionProperty::KIND_WRITE,
                    default => ReflectionProperty::KIND_DEFAULT
                };

                $isNullable = str_starts_with($matches[3][0], '?');
                $isArray = str_ends_with($matches[3][0], '[]');
                $isCollection = $matches[2][0] === 'Collection<';

                $textProperties->add(
                    (new ReflectionProperty())
                        ->setName(substr($matches[7][0], 1))
                        ->setType($matches[4][0])
                        ->setIsNullable($isNullable)
                        ->setIsArrayType($isArray || $isCollection)
                        ->setKind($kind)
                        ->setHasDefaultValue(false)
                        ->setHasPagination($matches[9][0] === '@paginate')
                        ->setHasFilters($matches[10][0] === '@filterable')
                        ->setHasOrder($matches[11][0] === '@orderable')
                        ->setIsComputed($matches[12][0] === '@computed')
                        ->setEagerLoadDisabled($matches[13][0] === '@eager-load-disabled')
                );
            }
        }

        return $textProperties;
    }
}
