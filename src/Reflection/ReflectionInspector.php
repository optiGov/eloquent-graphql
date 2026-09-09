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
            self::$cache['classDoc'][$class] = new Collection();

            return self::$cache['classDoc'][$class];
        }

        $properties = static::parsePropertiesFromClassDoc($doc);

        $fileName = $reflection->getFileName();
        $useMap   = $fileName !== false ? static::parseUseStatements($fileName) : [];

        $qualified = static::fullQualifyProperties($properties, $reflection->getNamespaceName(), $useMap);

        self::$cache['classDoc'][$class] = $qualified;

        return $qualified;
    }

    /**
     * Takes parsed properties and resolves every non-primitive, non-FQN type name
     * to its fully-qualified class name.
     *
     * Resolution order:
     *  1. Already FQN (contains "\") → kept as-is.
     *  2. Found in the file's `use` statements → use the imported FQN.
     *  3. Otherwise → prepend the model's own namespace (same-namespace shorthand).
     *
     * @param array<string, string> $useMap  short name → FQN, from parseUseStatements()
     */
    private static function fullQualifyProperties(Collection $properties, string $namespace, array $useMap = []): Collection
    {
        $properties
            ->filter(fn ($property) => ! $property->isPrimitiveType())
            ->filter(fn ($property) => ! str_contains($property->getType(), '\\'))
            ->each(function (ReflectionProperty $property) use ($namespace, $useMap) {
                $shortName = $property->getType();

                if (isset($useMap[$shortName])) {
                    // Resolved via a "use" import in the model file.
                    $property->setType($useMap[$shortName]);
                } else {
                    // Fall back to the model's own namespace (same-namespace shorthand).
                    $property->setType($namespace.'\\'.$shortName);
                }
            });

        return $properties;
    }

    /**
     * Parses all "use" import statements from a PHP source file and returns a
     * map of short name (or alias) → fully-qualified class name.
     *
     * Handles:
     *  - Simple imports:  use Foo\Bar\Baz;
     *  - Aliased imports: use Foo\Bar\Baz as MyBaz;
     *
     * Grouped imports (use Foo\{Bar, Baz};) are intentionally not supported here
     * as they are uncommon in model files.
     *
     * @return array<string, string>
     */
    private static function parseUseStatements(string $filePath): array
    {
        $source = @file_get_contents($filePath);
        if ($source === false) {
            return [];
        }

        $map = [];

        // Match: use Some\Namespace\ClassName;
        //   or:  use Some\Namespace\ClassName as Alias;
        preg_match_all(
            '/^use\s+([\w\\\\]+)(?:\s+as\s+(\w+))?\s*;/m',
            $source,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $fqn      = ltrim($match[1], '\\');
            $alias    = $match[2] ?? '';
            $segments = explode('\\', $fqn);

            // Use the explicit alias when given, otherwise the last FQN segment.
            $shortName       = $alias !== '' ? $alias : end($segments);
            $map[$shortName] = $fqn;
        }

        return $map;
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
            $regex = '/ ?\*? ?@property(-read|-write)? (Collection<)?(\??(\\\\?([A-Z]|[a-z]|[0-9]|_)+)+(\[\])?)>? (\$([A-Z]|[a-z]|[0-9]|_)+) ?(@paginate)? ?(@filterable)? ?(@orderable)? ?(@computed)? ?(@eager-load-disabled)? ?(@duplicateable)?/m';

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
                $bareType = rtrim(ltrim($matches[3][0], '?'), '[]');

                $textProperties->add(
                    (new ReflectionProperty())
                        ->setName(substr($matches[7][0], 1))
                        ->setType($bareType)
                        ->setIsNullable($isNullable)
                        ->setIsArrayType($isArray || $isCollection)
                        ->setKind($kind)
                        ->setHasDefaultValue(false)
                        ->setHasPagination($matches[9][0] === '@paginate')
                        ->setHasFilters($matches[10][0] === '@filterable')
                        ->setHasOrder($matches[11][0] === '@orderable')
                        ->setIsComputed($matches[12][0] === '@computed')
                        ->setEagerLoadDisabled($matches[13][0] === '@eager-load-disabled')
                        ->setIsDuplicateable($matches[14][0] === '@duplicateable')
                );
            }
        }

        return $textProperties;
    }
}
