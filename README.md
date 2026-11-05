# eloquent-graphql

This package automatically creates GraphQL types and fields with their resolvers for the `webonyx/graphql-php` library
from Eloquent models. The package
utilizes the PHP DocBlock annotations to determine the GraphQL types and fields.
It supports pagination, filtering and ordering on properties returning multiple models using a query builder
for optimal performance.

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

## Go Further

### Pagination

Use the `@paginate` annotation to paginate properties returning multiple models using a query builder - for example has
many relations.

```php
/**
 * ...
 * @property Books[] $books @paginate
 * ...
 */
class Author {
    // ...
}
```

### Filtering and Ordering

Use the `@filterable` and `@orderable` annotations to enable filtering and ordering on properties returning multiple
models using a query builder - for example has many relations.

```php
/**
 * ...
 * @property Books[] $books @paginate @filterable @orderable
 * ...
 */
class Author {
    // ...
}
```

### Authorization

Every model needs a Laravel policy implementing `EloquentGraphQLPolicyContract`. Besides the usual abilities, the
policy restricts queries to the models the user can view with `scopeViewable()`. Lists, counts, filters (including
filters on relations) only consider those models, so they cannot reveal hidden ones. The scope must yield exactly the
models for which `view()` returns true.

```php
class AuthorPolicy implements EloquentGraphQLPolicyContract
{
    public function scopeViewable(?Authenticatable $user, Builder $query): void
    {
        if ($user === null) {
            $query->where('visible', true);
        }
    }

    public function view(?Authenticatable $user, Model $model): bool
    {
        return $user !== null || $model->visible;
    }

    // ...
}
```

Ordering by a property requires the same permission as filtering by it (`filterProperty()`).

### Custom Fields

You can add custom fields to your GraphQL types using the `field()` method.

```php
$schema = new Schema([
    'query' => $graphQLService->query()
        ->view(Book::class)
        ->field('customField', [
            'type' => Type::string(),
            'resolve' => function ($root, $args) {
                return 'Hello World!';
            }
        ])
        ->build(),
]);