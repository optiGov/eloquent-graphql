<?php

namespace EloquentGraphQL\Tests\Security;

use EloquentGraphQL\Exceptions\GraphQLError;
use EloquentGraphQL\Factories\Pagination\PaginatorQuery;
use EloquentGraphQL\Services\EloquentGraphQLService;
use EloquentGraphQL\Tests\Models\Author;
use EloquentGraphQL\Tests\Models\Book;
use EloquentGraphQL\Tests\Models\Reader;
use EloquentGraphQL\Tests\Policies\AuthorPolicy;
use EloquentGraphQL\Tests\Policies\BookPolicy;
use Illuminate\Auth\Access\Gate;
use Illuminate\Auth\RequestGuard;
use Illuminate\Container\Container;
use Illuminate\Contracts\Auth\Access\Gate as GateContract;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

/**
 * Tests that filters, counts and orders only consider the models a guest can view, using an in-memory database.
 */
class ViewableScopeTest extends TestCase
{
    private EloquentGraphQLService $service;

    protected function setUp(): void
    {
        $container = new Container();
        Container::setInstance($container);

        $gate = new Gate($container, fn () => null);
        $gate->policy(Author::class, AuthorPolicy::class);
        $gate->policy(Book::class, BookPolicy::class);
        $container->instance(GateContract::class, $gate);
        $container->instance(AuthFactory::class, new class implements AuthFactory {
            public function guard($name = null): Guard
            {
                return new RequestGuard(fn () => null, new Request());
            }

            public function shouldUse($name): void {}
        });

        $capsule = new Capsule($container);
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        $capsule->schema()->create('authors', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email');
            $table->boolean('visible');
            $table->timestamps();
        });
        $capsule->schema()->create('books', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('author_id');
            $table->timestamps();
        });
        $capsule->schema()->create('readers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->timestamps();
        });

        $anna = $this->createAuthor('Anna', true);
        $bert = $this->createAuthor('Bert', false);
        $this->createAuthor('Öffentlich', false);

        $this->createBook('Sichtbar', $anna);
        $this->createBook('Versteckt', $bert);

        $this->service = new EloquentGraphQLService();
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);
    }

    // -----------------------------------------------------------------------
    // scope
    // -----------------------------------------------------------------------

    public function testCountIgnoresModelsTheUserCannotView(): void
    {
        $this->assertSame(2, $this->makePaginator(Author::class)->count());
    }

    public function testGetIgnoresModelsTheUserCannotView(): void
    {
        $names = $this->makePaginator(Author::class)->get()->pluck('name')->all();

        $this->assertSame(['Anna', 'Öffentlich'], $names);
    }

    public function testFilterDoesNotMatchModelsTheUserCannotView(): void
    {
        $paginator = $this->makePaginator(Author::class)->filter(['name' => ['eq' => 'Bert']]);

        $this->assertSame(0, $paginator->count());
    }

    public function testScopeIsNestedSoItCannotWidenTheFilter(): void
    {
        $paginator = $this->makePaginator(Author::class)->filter(['name' => ['like' => 'B%']]);

        $this->assertSame(0, $paginator->count());
    }

    public function testScopeIsAppliedOnlyOnce(): void
    {
        $paginator = $this->makePaginator(Author::class);
        $paginator->count();
        $paginator->getQueryBuilder();

        $this->assertCount(1, $paginator->getQueryBuilder()->getQuery()->wheres);
    }

    public function testRelationFilterDoesNotMatchModelsTheUserCannotView(): void
    {
        $hiddenAuthorPaginator = $this->makePaginator(Book::class)->filter(['author' => ['name' => ['eq' => 'Bert']]]);
        $visibleAuthorPaginator = $this->makePaginator(Book::class)->filter(['author' => ['name' => ['eq' => 'Anna']]]);

        $this->assertSame(0, $hiddenAuthorPaginator->count());
        $this->assertSame(1, $visibleAuthorPaginator->count());
    }

    public function testModelsWithoutPolicyAreNotScoped(): void
    {
        Reader::query()->insert([['name' => 'Clara'], ['name' => 'Dora']]);

        $this->assertSame(2, $this->makePaginator(Reader::class)->count());
    }

    public function testFilterViewableKeepsOnlyViewableModels(): void
    {
        $names = $this->service->security()->filterViewable(Author::query()->get())->pluck('name')->all();

        $this->assertSame(['Anna', 'Öffentlich'], $names);
    }

    // -----------------------------------------------------------------------
    // order
    // -----------------------------------------------------------------------

    public function testOrderByAuthorizedPropertyIsApplied(): void
    {
        $names = $this->makePaginator(Author::class)->order(['name' => ['order' => 'desc']])->get()->pluck('name')->all();

        $this->assertSame(['Öffentlich', 'Anna'], $names);
    }

    public function testOrderByUnauthorizedPropertyThrows(): void
    {
        $this->expectException(GraphQLError::class);
        $this->expectExceptionMessage('You are not authorized to order by the property [email].');

        $this->makePaginator(Author::class)->order(['email' => ['order' => 'asc']]);
    }

    public function testOrderByUnauthorizedPropertyOfRelationThrows(): void
    {
        $this->expectException(GraphQLError::class);
        $this->expectExceptionMessage('You are not authorized to order by the property [email].');

        $this->makePaginator(Book::class)->order(['author' => ['email' => ['order' => 'asc']]]);
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

    private function createBook(string $name, Author $author): void
    {
        $book = new Book();
        $book->forceFill(['name' => $name, 'author_id' => $author->id])->save();
    }
}
