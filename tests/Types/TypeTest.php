<?php

namespace EloquentGraphQL\Tests\Types;

use EloquentGraphQL\Services\EloquentGraphQLService;
use EloquentGraphQL\Tests\Models\Author;
use EloquentGraphQL\Tests\Models\Book;
use GraphQL\Type\Definition\Argument;
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

    public function testArgsPagination()
    {
        $graphql = new EloquentGraphQLService();
        $authorType = $graphql->typeFactory(Author::class)->build();
        $booksField = $authorType->getField('books');

        $this->assertInstanceOf(Type::class, $authorType);
        $this->assertInstanceOf(Argument::class, $booksField->getArg('limit'));
        $this->assertInstanceOf(Argument::class, $booksField->getArg('offset'));
        $this->assertInstanceOf(Type::int()::class, $booksField->getArg('limit')->getType());
        $this->assertInstanceOf(Type::int()::class, $booksField->getArg('offset')->getType());
    }

    public function testNoArgsPagination()
    {
        $graphql = new EloquentGraphQLService();
        $authorType = $graphql->typeFactory(Book::class)->build();
        $booksField = $authorType->getField('readers');

        $this->assertInstanceOf(Type::class, $authorType);
        $this->assertNull($booksField->getArg('limit'));
        $this->assertNull($booksField->getArg('offset'));
    }
}
