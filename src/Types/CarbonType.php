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
    public string $name = 'Carbon';

    /**
     * @throws Error
     */
    public function serialize($value)
    {
        if (! ($value instanceof Carbon)) {
            throw new InvariantViolation('Could not serialize following value as Carbon: '.Utils::printSafe($value));
        }

        return $this->parseValue($value);
    }

    public function parseValue($value)
    {
        if (! ($value instanceof Carbon)) {
            throw new Error('Cannot represent following value as email: '.Utils::printSafeJson($value));
        }

        return $value->format('Y-m-d H:i:s');
    }

    public function parseLiteral(Node $valueNode, array $variables = null)
    {
        // Throw GraphQL\Error\Error vs \UnexpectedValueException to locate the error in the query
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
