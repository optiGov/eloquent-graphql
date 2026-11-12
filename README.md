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

Every model needs a Laravel policy implementing `EloquentGraphQLPolicyContract`. Models without such a policy are not
viewable. Besides the usual abilities, `visibility()` defines which models the user can view:

```php
class AuthorPolicy implements EloquentGraphQLPolicyContract
{
    public function visibility(?Authenticatable $user): Visibility
    {
        if ($user !== null) {
            return Visibility::all();
        }

        return Visibility::where(fn (Builder $query) => $query->where($query->qualifyColumn('visible'), true));
    }

    // ...
}
```

`Visibility::all()`, `Visibility::none()` and `Visibility::allIf(bool)` need no database query. Non-Eloquent types may
only use those.

- Every query of the package (lists, counts, single entries, relations, filters and orders on relations, and the models
  looked up by mutations) is restricted with `security()->constrain()`. Hidden models are neither returned nor counted,
  and a hidden single entry or has-one relation resolves to `null`.
- Every field of a model checks `security()->canView()` first. Models loaded through `constrain()` are known to be
  viewable, so this costs no query. Any other model, e.g. one returned by a custom resolver, is checked once against the
  visibility. Models created within the request are viewable. `security()->canViewAs($model, $user)` checks a model for
  another user, e.g. in a policy's `view()`.
- Models connected by mutations are restricted with `connectability()`, which is usually `visibility()`. It may allow
  more, e.g. freshly uploaded files that nobody can view yet. Connectable models are not remembered as viewable.
- Ordering by a property requires the same permission as filtering by it (`filterProperty()`).

Custom resolvers that act on a model should check it before doing so, or load it through `constrain()`:

```php
$book = Book::findOrFail($args['id']);
$graphQLService->security()->assertCanView($book);
```

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