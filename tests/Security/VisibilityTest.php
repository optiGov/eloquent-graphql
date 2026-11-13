<?php

namespace EloquentGraphQL\Tests\Security;

use EloquentGraphQL\Exceptions\GraphQLError;
use EloquentGraphQL\Factories\Pagination\PaginatorQuery;
use EloquentGraphQL\Security\Visibility;
use EloquentGraphQL\Services\EloquentGraphQLService;
use EloquentGraphQL\Tests\Models\Anthology;
use EloquentGraphQL\Tests\Models\Author;
use EloquentGraphQL\Tests\Models\Book;
use EloquentGraphQL\Tests\Models\Reader;
use EloquentGraphQL\Tests\Policies\AuthorPolicy;
use EloquentGraphQL\Tests\Policies\BookPolicy;
use GraphQL\GraphQL;
use GraphQL\Type\Definition\Type;
use GraphQL\Type\Schema;
use Illuminate\Auth\Access\Gate;
use Illuminate\Auth\RequestGuard;
use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Events\Dispatcher;
use Illuminate\Http\Request;
use LogicException;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Tests that guests only get to see, count, filter, order and change the models they can view, using an in-memory
 * database. Authors are partially visible, books and anthologies are visible, readers have no policy.
 */
class VisibilityTest extends TestCase
{
    private EloquentGraphQLService $service;

    private Connection $connection;

    private Author $anna;

    private Author $bert;

    private Book $sichtbar;

    private Book $versteckt;

    protected function setUp(): void
    {
        $container = new Container();
        Container::setInstance($container);

        $gate = new Gate($container, fn () => null);
        $gate->policy(Author::class, AuthorPolicy::class);
        $gate->policy(Book::class, BookPolicy::class);
        $gate->policy(Anthology::class, BookPolicy::class);
        $gate->policy(stdClass::class, AuthorPolicy::class);
        $container->instance(GateContract::class, $gate);
        $container->instance(AuthFactory::class, new class implements AuthFactory
        {
            public function guard($name = null): Guard
            {
                return new RequestGuard(fn () => null, new Request());
            }

            public function shouldUse($name): void {}
        });

        $capsule = new Capsule($container);
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $capsule->setEventDispatcher(new Dispatcher($container));
        $container->instance('events', $capsule->getEventDispatcher());
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
        $this->connection = $capsule->getConnection();

        $capsule->schema()->create('authors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->boolean('visible');
            $table->foreignId('anthology_id')->nullable();
            $table->foreignId('chief_of_anthology_id')->nullable();
            $table->timestamps();
        });
        $capsule->schema()->create('anthologies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('editor_id')->nullable();
            $table->timestamps();
        });
        $capsule->schema()->create('anthology_author', function (Blueprint $table) {
            $table->foreignId('anthology_id');
            $table->foreignId('author_id');
        });
        $capsule->schema()->create('books', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('author_id')->nullable();
            $table->timestamps();
        });
        $capsule->schema()->create('readers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        $this->anna = $this->createAuthor('Anna', true);
        $this->bert = $this->createAuthor('Bert', false);
        $this->createAuthor('Öffentlich', false);

        $this->sichtbar = $this->createBook('Sichtbar', $this->anna);
        $this->versteckt = $this->createBook('Versteckt', $this->bert);

        $this->service = new EloquentGraphQLService();
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);
    }

    // -----------------------------------------------------------------------
    // lists, counts, filters and orders
    // -----------------------------------------------------------------------

    public function test_count_ignores_models_the_user_cannot_view(): void
    {
        $this->assertSame(2, $this->makePaginator(Author::class)->count());
    }

    public function test_get_ignores_models_the_user_cannot_view(): void
    {
        $names = $this->makePaginator(Author::class)->get()->pluck('name')->all();

        $this->assertSame(['Anna', 'Öffentlich'], $names);
    }

    public function test_filter_does_not_match_models_the_user_cannot_view(): void
    {
        $paginator = $this->makePaginator(Author::class)->filter(['name' => ['eq' => 'Bert']]);

        $this->assertSame(0, $paginator->count());
    }

    public function test_visibility_is_nested_so_it_cannot_widen_the_filter(): void
    {
        $paginator = $this->makePaginator(Author::class)->filter(['name' => ['like' => 'B%']]);

        $this->assertSame(0, $paginator->count());
    }

    public function test_or_filter_cannot_widen_the_visibility(): void
    {
        $paginator = $this->makePaginator(Author::class)->filter([
            'or' => [['visible' => ['eq' => false]], ['name' => ['eq' => 'Bert']]],
        ]);

        $this->assertSame(['Öffentlich'], $paginator->get()->pluck('name')->all());
        $this->assertSame(1, $paginator->count());
    }

    public function test_not_filter_cannot_widen_the_visibility(): void
    {
        $paginator = $this->makePaginator(Author::class)->filter(['not' => ['name' => ['eq' => 'Anna']]]);

        $this->assertSame(['Öffentlich'], $paginator->get()->pluck('name')->all());
        $this->assertSame(1, $paginator->count());
    }

    public function test_visibility_is_applied_only_once(): void
    {
        $paginator = $this->makePaginator(Author::class);
        $paginator->count();
        $paginator->getQueryBuilder();

        $this->assertCount(1, $paginator->getQueryBuilder()->getQuery()->wheres);
    }

    public function test_relation_filter_does_not_match_models_the_user_cannot_view(): void
    {
        $hiddenAuthorPaginator = $this->makePaginator(Book::class)->filter(['author' => ['name' => ['eq' => 'Bert']]]);
        $visibleAuthorPaginator = $this->makePaginator(Book::class)->filter(['author' => ['name' => ['eq' => 'Anna']]]);

        $this->assertSame(0, $hiddenAuthorPaginator->count());
        $this->assertSame(1, $visibleAuthorPaginator->count());
    }

    public function test_relation_order_ignores_models_the_user_cannot_view(): void
    {
        $names = $this->makePaginator(Book::class)->order(['author' => ['name' => ['order' => 'desc']]])->get()->pluck('name')->all();

        $this->assertSame(['Sichtbar', 'Versteckt'], $names);
    }

    public function test_models_without_policy_are_not_viewable_and_not_queried(): void
    {
        Reader::query()->insert([['name' => 'Clara'], ['name' => 'Dora']]);
        $paginator = $this->makePaginator(Reader::class);

        $queryCount = $this->countQueries(function () use ($paginator) {
            $this->assertSame(0, $paginator->count());
            $this->assertCount(0, $paginator->get());
        });

        $this->assertSame(0, $queryCount);
    }

    public function test_order_by_authorized_property_is_applied(): void
    {
        $names = $this->makePaginator(Author::class)->order(['name' => ['order' => 'desc']])->get()->pluck('name')->all();

        $this->assertSame(['Öffentlich', 'Anna'], $names);
    }

    public function test_order_by_unauthorized_property_throws(): void
    {
        $this->expectException(GraphQLError::class);
        $this->expectExceptionMessage('You are not authorized to order by the property [email].');

        $this->makePaginator(Author::class)->order(['email' => ['order' => 'asc']]);
    }

    public function test_order_by_unauthorized_property_of_relation_throws(): void
    {
        $this->expectException(GraphQLError::class);
        $this->expectExceptionMessage('You are not authorized to order by the property [email].');

        $this->makePaginator(Book::class)->order(['author' => ['email' => ['order' => 'asc']]]);
    }

    // -----------------------------------------------------------------------
    // canView
    // -----------------------------------------------------------------------

    public function test_models_loaded_through_constrain_are_viewable_without_query(): void
    {
        $authors = $this->service->security()->constrain(Author::query())->get();

        $queryCount = $this->countQueries(function () use ($authors) {
            foreach ($authors as $author) {
                $this->assertTrue($this->service->security()->canView($author));
            }
        });

        $this->assertSame(0, $queryCount);
    }

    public function test_pluck_through_constrain_only_returns_viewable_values(): void
    {
        $names = $this->service->security()->constrain(Author::query())->pluck('name')->all();

        $this->assertSame(['Anna', 'Öffentlich'], $names);
    }

    public function test_constraint_without_condition_matches_nothing(): void
    {
        $visibility = Visibility::where(fn () => null);

        $this->assertSame(0, $this->service->security()->constrain(Author::query(), $visibility)->count());
    }

    public function test_constraint_starting_with_or_where_cannot_widen_relation_filter(): void
    {
        $visibility = Visibility::where(fn (Builder $query) => $query->orWhere('visible', true));

        $names = Book::query()->whereHas('author', $visibility->getConstraint())->pluck('name')->all();

        $this->assertSame(['Sichtbar'], $names);
    }

    public function test_other_models_are_checked_once(): void
    {
        $anna = Author::query()->find($this->anna->id);

        $queryCount = $this->countQueries(function () use ($anna) {
            $this->assertTrue($this->service->security()->canView($anna));
            $this->assertTrue($this->service->security()->canView($anna));
        });

        $this->assertSame(1, $queryCount);
    }

    public function test_hidden_model_is_not_viewable(): void
    {
        $bert = Author::query()->find($this->bert->id);

        $this->assertFalse($this->service->security()->canView($bert));
    }

    public function test_model_created_within_the_request_is_viewable(): void
    {
        $this->assertTrue($this->service->security()->canView($this->createAuthor('Neu', false)));
    }

    public function test_model_created_within_the_request_is_checked_for_an_explicit_user(): void
    {
        $this->assertFalse($this->service->security()->canViewAs($this->createAuthor('Neu', false), null));
    }

    public function test_model_without_policy_is_not_viewable(): void
    {
        $this->assertFalse($this->service->security()->canView(Reader::query()->forceCreate(['name' => 'Clara'])->fresh()));
    }

    public function test_non_eloquent_type_with_query_constraint_throws(): void
    {
        $this->expectException(LogicException::class);

        $this->service->security()->canView(new stdClass);
    }

    // -----------------------------------------------------------------------
    // GraphQL
    // -----------------------------------------------------------------------

    public function test_list_returns_and_counts_only_viewable_models_with_two_queries(): void
    {
        $result = null;
        $queryCount = $this->countQueries(function () use (&$result) {
            $result = $this->execute('{ allAuthors { totalCount edges { node { name } } } }');
        });

        $this->assertSame(2, $result['data']['allAuthors']['totalCount']);
        $this->assertSame(['Anna', 'Öffentlich'], array_column(array_column($result['data']['allAuthors']['edges'], 'node'), 'name'));
        $this->assertSame(2, $queryCount);
    }

    public function test_hidden_single_entry_resolves_to_null(): void
    {
        $result = $this->execute("{ author(id: {$this->bert->id}) { name } }");

        $this->assertNull($result['data']['author']);
        $this->assertArrayNotHasKey('errors', $result);
    }

    public function test_hidden_required_relation_is_not_exposed(): void
    {
        $result = $this->execute("{ book(id: {$this->versteckt->id}) { name author { name } } }");

        $this->assertNull($result['data']['book']);
        $this->assertSame(['book', 'author'], $result['errors'][0]['path']);
    }

    public function test_hidden_model_returned_by_custom_resolver_is_not_exposed(): void
    {
        $result = $this->execute('{ customAuthor(name: "Bert") { name } }');

        $this->assertNull($result['data']['customAuthor']);
        $this->assertSame(['customAuthor', 'name'], $result['errors'][0]['path']);
    }

    public function test_visible_model_returned_by_custom_resolver_is_exposed(): void
    {
        $result = $this->execute('{ customAuthor(name: "Anna") { name } }');

        $this->assertSame('Anna', $result['data']['customAuthor']['name']);
    }

    public function test_mutation_does_not_find_hidden_model(): void
    {
        $hiddenResult = $this->execute("mutation { deleteAuthor(id: {$this->bert->id}) }");
        $visibleResult = $this->execute("mutation { deleteAuthor(id: {$this->anna->id}) }");

        $this->assertFalse($hiddenResult['data']['deleteAuthor']);
        $this->assertTrue($visibleResult['data']['deleteAuthor']);
        $this->assertTrue(Author::query()->whereKey($this->bert->id)->exists());
    }

    public function test_mutation_does_not_connect_hidden_model(): void
    {
        $oeffentlich = Author::query()->where('name', 'Öffentlich')->first();

        $hiddenResult = $this->execute("mutation { updateBook(id: {$this->sichtbar->id}, book: { name: \"Sichtbar\", author: {$this->bert->id} }) }");
        $this->assertSame('You are not authorized to update this model.', $hiddenResult['errors'][0]['message']);
        $this->assertSame($this->anna->id, $this->sichtbar->fresh()->author_id);

        $this->execute("mutation { updateBook(id: {$this->sichtbar->id}, book: { name: \"Sichtbar\", author: {$oeffentlich->id} }) }");
        $this->assertSame($oeffentlich->id, $this->sichtbar->fresh()->author_id);
    }

    public function test_update_aborts_when_belongs_to_many_would_remove_hidden_model(): void
    {
        $anthology = $this->createAnthology();
        $anthology->authors()->attach([$this->anna->id, $this->bert->id]);

        $result = $this->updateAnthology($anthology, "name: \"Geändert\", authors: [{$this->anna->id}]");

        $this->assertSame('You are not authorized to update this model.', $result['errors'][0]['message']);
        $this->assertEqualsCanonicalizing([$this->anna->id, $this->bert->id], $anthology->authors()->pluck('authors.id')->all());
        $this->assertSame('Sammelband', $anthology->fresh()->name);
    }

    public function test_update_keeps_unchanged_hidden_model_of_belongs_to_many(): void
    {
        $anthology = $this->createAnthology();
        $anthology->authors()->attach([$this->anna->id, $this->bert->id]);
        $verknuepfbar = $this->createAuthor('Verknüpfbar', false);

        $result = $this->updateAnthology($anthology, "name: \"Sammelband\", authors: [{$this->anna->id}, {$this->bert->id}, {$verknuepfbar->id}]");

        $this->assertArrayNotHasKey('errors', $result);
        $this->assertEqualsCanonicalizing([$this->anna->id, $this->bert->id, $verknuepfbar->id], $anthology->authors()->pluck('authors.id')->all());
    }

    public function test_update_aborts_when_belongs_to_many_would_connect_hidden_or_missing_model(): void
    {
        $anthology = $this->createAnthology();

        $hiddenResult = $this->updateAnthology($anthology, "name: \"Sammelband\", authors: [{$this->bert->id}]");
        $missingResult = $this->updateAnthology($anthology, 'name: "Sammelband", authors: [999999]');

        $this->assertSame('You are not authorized to update this model.', $hiddenResult['errors'][0]['message']);
        $this->assertSame('You are not authorized to update this model.', $missingResult['errors'][0]['message']);
        $this->assertSame(0, $anthology->authors()->count());
    }

    public function test_update_aborts_when_has_many_would_remove_hidden_model(): void
    {
        $anthology = $this->createAnthology();
        $anthology->members()->saveMany([$this->anna, $this->bert]);

        $result = $this->updateAnthology($anthology, "name: \"Sammelband\", members: [{$this->anna->id}]");

        $this->assertSame('You are not authorized to update this model.', $result['errors'][0]['message']);
        $this->assertSame($anthology->id, $this->bert->fresh()->anthology_id);
    }

    public function test_update_removes_visible_model_of_has_many(): void
    {
        $anthology = $this->createAnthology();
        $anthology->members()->saveMany([$this->anna, $this->bert]);

        $result = $this->updateAnthology($anthology, "name: \"Sammelband\", members: [{$this->bert->id}]");

        $this->assertArrayNotHasKey('errors', $result);
        $this->assertNull($this->anna->fresh()->anthology_id);
        $this->assertSame($anthology->id, $this->bert->fresh()->anthology_id);
    }

    public function test_update_aborts_when_belongs_to_would_remove_or_replace_hidden_model(): void
    {
        $anthology = $this->createAnthology(editor: $this->bert);

        $removeResult = $this->updateAnthology($anthology, 'name: "Sammelband", editor: null');
        $replaceResult = $this->updateAnthology($anthology, "name: \"Sammelband\", editor: {$this->anna->id}");

        $this->assertSame('You are not authorized to update this model.', $removeResult['errors'][0]['message']);
        $this->assertSame('You are not authorized to update this model.', $replaceResult['errors'][0]['message']);
        $this->assertSame($this->bert->id, $anthology->fresh()->editor_id);
    }

    public function test_update_aborts_when_has_one_would_remove_hidden_model(): void
    {
        $anthology = $this->createAnthology();
        $anthology->chief()->save($this->bert);

        $result = $this->updateAnthology($anthology, 'name: "Sammelband", chief: null');

        $this->assertSame('You are not authorized to update this model.', $result['errors'][0]['message']);
        $this->assertSame($anthology->id, $this->bert->fresh()->chief_of_anthology_id);
    }

    public function test_mutation_connects_connectable_model_without_exposing_it(): void
    {
        $verknuepfbar = $this->createAuthor('Verknüpfbar', false);

        $this->execute("mutation { updateBook(id: {$this->sichtbar->id}, book: { name: \"Sichtbar\", author: {$verknuepfbar->id} }) }");

        $this->assertSame($verknuepfbar->id, $this->sichtbar->fresh()->author_id);
        $this->assertFalse($this->service->security()->canView(Author::query()->find($verknuepfbar->id)));
    }

    public function test_create_aborts_when_belongs_to_is_not_connectable(): void
    {
        $result = Book::unguarded(fn () => $this->execute("mutation { createBook(book: { name: \"Neu\", author: {$this->bert->id} }) { id } }"));

        $this->assertSame('You are not authorized to create this model.', $result['errors'][0]['message']);
        $this->assertFalse(Book::query()->where('name', 'Neu')->exists());
    }

    public function test_create_aborts_for_missing_belongs_to_with_the_same_error(): void
    {
        $result = Book::unguarded(fn () => $this->execute('mutation { createBook(book: { name: "Neu", author: 999999 }) { id } }'));

        $this->assertSame('You are not authorized to create this model.', $result['errors'][0]['message']);
        $this->assertFalse(Book::query()->where('name', 'Neu')->exists());
    }

    public function test_create_aborts_when_many_relation_is_not_connectable(): void
    {
        $belongsToManyResult = Anthology::unguarded(fn () => $this->execute("mutation { createAnthology(anthology: { name: \"Neu\", authors: [{$this->anna->id}, {$this->bert->id}] }) { id } }"));
        $hasManyResult = Anthology::unguarded(fn () => $this->execute("mutation { createAnthology(anthology: { name: \"Neu\", members: [{$this->bert->id}] }) { id } }"));

        $this->assertSame('You are not authorized to create this model.', $belongsToManyResult['errors'][0]['message']);
        $this->assertSame('You are not authorized to create this model.', $hasManyResult['errors'][0]['message']);
        $this->assertFalse(Anthology::query()->where('name', 'Neu')->exists());
        $this->assertNull($this->bert->fresh()->anthology_id);
    }

    public function test_create_aborts_when_has_one_is_not_connectable(): void
    {
        $result = Anthology::unguarded(fn () => $this->execute("mutation { createAnthology(anthology: { name: \"Neu\", chief: {$this->bert->id} }) { id } }"));

        $this->assertSame('You are not authorized to create this model.', $result['errors'][0]['message']);
        $this->assertFalse(Anthology::query()->where('name', 'Neu')->exists());
    }

    public function test_create_connects_connectable_many_relation(): void
    {
        $verknuepfbar = $this->createAuthor('Verknüpfbar', false);

        $result = Anthology::unguarded(fn () => $this->execute("mutation { createAnthology(anthology: { name: \"Neu\", authors: [{$this->anna->id}, {$verknuepfbar->id}] }) { id } }"));

        $this->assertArrayNotHasKey('errors', $result);
        $this->assertEqualsCanonicalizing([$this->anna->id, $verknuepfbar->id], Anthology::query()->where('name', 'Neu')->first()->authors()->pluck('authors.id')->all());
    }

    public function test_create_connects_connectable_belongs_to(): void
    {
        $verknuepfbar = $this->createAuthor('Verknüpfbar', false);

        $result = Book::unguarded(fn () => $this->execute("mutation { createBook(book: { name: \"Neu\", author: {$verknuepfbar->id} }) { id } }"));

        $this->assertArrayNotHasKey('errors', $result);
        $this->assertSame($verknuepfbar->id, Book::query()->where('name', 'Neu')->value('author_id'));
    }

    private function execute(string $query): array
    {
        $schema = new Schema([
            'query' => $this->service->query()
                ->all(Author::class)
                ->view(Author::class)
                ->view(Book::class)
                ->field('customAuthor', [
                    'type' => $this->service->typeFactory(Author::class)->build(),
                    'args' => ['name' => ['type' => Type::nonNull(Type::string())]],
                    'resolve' => fn ($_, array $args) => Author::query()->where('name', $args['name'])->first(),
                ])
                ->build(),
            'mutation' => $this->service->mutation()
                ->delete(Author::class)
                ->create(Book::class)
                ->create(Anthology::class)
                ->update(Book::class)
                ->update(Anthology::class)
                ->build(),
        ]);

        return GraphQL::executeQuery($schema, $query)->toArray();
    }

    private function countQueries(callable $callback): int
    {
        $this->connection->flushQueryLog();
        $this->connection->enableQueryLog();
        $callback();
        $this->connection->disableQueryLog();

        return count($this->connection->getQueryLog());
    }

    private function makePaginator(string $className): PaginatorQuery
    {
        return (new PaginatorQuery(call_user_func("$className::query")))
            ->className($className)
            ->service($this->service);
    }

    private function createAuthor(string $name, bool $visible): Author
    {
        $author = new Author();
        $author->forceFill(['name' => $name, 'email' => strtolower($name).'@example.org', 'visible' => $visible])->save();

        return $author;
    }

    private function createBook(string $name, Author $author): Book
    {
        $book = new Book();
        $book->forceFill(['name' => $name, 'author_id' => $author->id])->save();

        return $book;
    }

    private function createAnthology(?Author $editor = null): Anthology
    {
        $anthology = new Anthology();
        $anthology->forceFill(['name' => 'Sammelband', 'editor_id' => $editor?->id])->save();

        return $anthology;
    }

    private function updateAnthology(Anthology $anthology, string $input): array
    {
        return $this->execute("mutation { updateAnthology(id: {$anthology->id}, anthology: { $input }) }");
    }
}
