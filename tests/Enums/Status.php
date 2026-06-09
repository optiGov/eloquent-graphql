<?php

namespace EloquentGraphQL\Tests\Enums;

/**
 * A string-backed enum used as a test fixture for GraphQL enum-type support.
 */
enum Status: string
{
    case Active   = 'active';
    case Inactive = 'inactive';
    case Pending  = 'pending';
}
