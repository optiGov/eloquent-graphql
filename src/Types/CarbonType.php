<?php

namespace EloquentGraphQL\Types;

use Carbon\Carbon;
use GraphQL\Error\Error;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Type\Definition\ScalarType;
use GraphQL\Utils\Utils;

class CarbonType extends ScalarType
{
    public string $name = 'DateTime';

    public function serialize($value)
    {
        // check if value is instance of Carbon
        if (! ($value instanceof Carbon)) {
            throw new InvariantViolation('Could not serialize following value as datetime: '.Utils::printSafe($value).'. A Carbon object is expected.');
        }

        return $value->format('Y-m-d H:i:s');
    }

    public function parseValue($value)
    {
        // check if value is parsable as date
        $date = Carbon::parse($value);
        if (! $date) {
            throw new Error('Cannot represent following value as timestamp: '.Utils::printSafeJson($value));
        }

        return $date->format('Y-m-d H:i:s');
    }

    public function parseLiteral(Node $valueNode, array $variables = null)
    {
        // throw GraphQL\Error\Error vs \UnexpectedValueException to locate the error in the query
        if (! $valueNode instanceof StringValueNode) {
            throw new Error('Query error: Can only parse strings got: '.$valueNode->kind, [$valueNode]);
        }

        // validate if parsable as date
        $date = Carbon::parse($valueNode->value);
        if (! $date) {
            throw new Error('Not a valid date', [$valueNode]);
        }

        return $date;
    }
}
