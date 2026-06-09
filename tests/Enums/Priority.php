<?php

namespace EloquentGraphQL\Tests\Enums;

/**
 * An integer-backed enum used as a test fixture.
 */
enum Priority: int
{
    case Low    = 1;
    case Medium = 2;
    case High   = 3;
}
