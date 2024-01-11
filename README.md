# eloquent-graphql
Automatic creation of GraphQL types and fields from Eloquent Models.

## Installation

```bash
composer require optigov/eloquent-graphql
```

## Usage

### Annotate your Models
In order to make fields available in GraphQL, annotate your Models with the `@property` annotation.
```php
/**
 * @property int $id
 * @property string $name
 * @property Author $author
 * @property-read $created_at
 * @property-read $updated_at
 */ 
class Book {
    // ...
}
```

```php
/**
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property Books[] $books
 * @property-read $created_at
 * @property-read $updated_at
 */ 
class Author {
    // ...
}
```

### Build your Schema
Build your Schema using the `EloquentGraphQLService` class.
```php
use App\Models\Book;
use App\Models\Author;
use GraphQL\Type\Schema;
use EloquentGraphQL\Services\EloquentGraphQLService;

$graphQLService = new EloquentGraphQLService();

$schema = new Schema([
    'query' => $graphQLService->query()
        ->view(Book::class)
        ->view(Author::class)
        ->all(Book::class)
        ->all(Author::class)
        ->build(),
    'mutation' => $graphQLService->mutation()
        ->create(Book::class)
        ->create(Author::class)
        ->update(Book::class)
        ->update(Author::class)
        ->delete(Book::class)
        ->delete(Author::class)
        ->build(),
]);
```
