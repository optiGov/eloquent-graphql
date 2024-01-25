<?php

namespace EloquentGraphQL\Tests\Fields;

use EloquentGraphQL\Services\EloquentGraphQLService;
use EloquentGraphQL\Tests\Models\Author;
use EloquentGraphQL\Tests\Models\Book;
use GraphQL\Type\Definition\Type;
use PHPUnit\Framework\TestCase;

class FieldTest extends TestCase
{
    public function testQueryCreation()
    {
        $graphql = new EloquentGraphQLService();

        $query = $graphql->query();

        $query->all(Book::class)
            ->all(Author::class)
            ->view(Book::class)
            ->view(Author::class);

        $type = $query->build();

        $this->assertInstanceOf(Type::class, $type);
        $this->assertSame('Query', $type->name);

        $this->assertSame('BookConnection!', $type->getField('allBooks')->getType()->toString());
        $this->assertSame('AuthorConnection!', $type->getField('allAuthors')->getType()->toString());
        $this->assertSame('Book', $type->getField('book')->getType()->toString());
        $this->assertSame('Author', $type->getField('author')->getType()->toString());
    }

    public function testMutationCreation()
    {
        $graphql = new EloquentGraphQLService();

        $mutation = $graphql->mutation();

        $mutation->create(Book::class)
            ->delete(Book::class)
            ->update(Book::class);

        $type = $mutation->build();

        $this->assertInstanceOf(Type::class, $type);
        $this->assertSame('Mutation', $type->name);

        $this->assertSame('Book!', $type->getField('createBook')->getType()->toString());
        $this->assertSame('Boolean!', $type->getField('deleteBook')->getType()->toString());
        $this->assertSame('Boolean!', $type->getField('updateBook')->getType()->toString());
    }
}
