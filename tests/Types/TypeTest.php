<?php

namespace EloquentGraphQL\Tests\Types;

use EloquentGraphQL\Services\EloquentGraphQLService;
use EloquentGraphQL\Tests\Models\Book;
use GraphQL\Type\Definition\Type;
use PHPUnit\Framework\TestCase;

class TypeTest extends TestCase
{
    public function testTypeCreation()
    {
        $graphql = new EloquentGraphQLService();
        $bookType = $graphql->typeFactory(Book::class)->build();

        $this->assertInstanceOf(Type::class, $bookType);
        $this->assertSame('Book', $bookType->name);

        $this->assertSame('Int!', $bookType->getField('id')->getType()->toString());
        $this->assertSame('String!', $bookType->getField('name')->getType()->toString());
        $this->assertSame('Author!', $bookType->getField('author')->getType()->toString());
    }
}
