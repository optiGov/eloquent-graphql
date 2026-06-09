<?php

namespace EloquentGraphQL\Tests\Pagination;

use EloquentGraphQL\Exceptions\GraphQLError;
use EloquentGraphQL\Factories\Pagination\PaginatorQuery;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the boolean filter combinators: and, or, not.
 *
 * Uses the same BuilderSpy and TestablePaginatorQuery from PaginatorQueryFilterTest,
 * but in a separate file to keep test concerns focussed.
 */

/**
 * Minimal Eloquent Builder spy that records top-level calls and supports
 * nested closures passed to whereNot / orWhere / where.
 *
 * We capture both the method name and the closure argument so that tests
 * can verify that the correct combinators are wired up.
 */
class BoolBuilderSpy extends EloquentBuilder
{
    public array $calls = [];
    private Model $modelStub;

    public function __construct(string $table = 'items')
    {
        $this->modelStub = new class($table) extends Model {
            private string $t;
            public function __construct(string $t) { $this->t = $t; }
            public function getTable(): string { return $this->t; }
        };
    }

    public function getModel(): Model { return $this->modelStub; }

    public function where($column, $operator = null, $value = null, $boolean = 'and'): static
    {
        if ($column instanceof \Closure) {
            $this->calls[] = ['method' => 'where(closure)'];
            $nested = new self();
            $column($nested);
            $this->calls[] = ['nested' => $nested->calls];
        } else {
            $this->calls[] = ['method' => 'where', 'args' => [$column, $operator, $value]];
        }
        return $this;
    }

    public function orWhere($column, $operator = null, $value = null): static
    {
        if ($column instanceof \Closure) {
            $this->calls[] = ['method' => 'orWhere(closure)'];
            $nested = new self();
            $column($nested);
            $this->calls[] = ['nested' => $nested->calls];
        } else {
            $this->calls[] = ['method' => 'orWhere', 'args' => [$column, $operator, $value]];
        }
        return $this;
    }

    public function whereNot($column, $operator = null, $value = null, $boolean = 'and'): static
    {
        $this->calls[] = ['method' => 'whereNot'];
        if ($column instanceof \Closure) {
            $nested = new self();
            $column($nested);
            $this->calls[] = ['nested' => $nested->calls];
        }
        return $this;
    }

    public function whereNull($columns, $boolean = 'and', $not = false): static
    {
        $this->calls[] = ['method' => 'whereNull', 'args' => [$columns]];
        return $this;
    }

    public function whereNotNull($columns = ['*'], $boolean = 'and'): static
    {
        $this->calls[] = ['method' => 'whereNotNull', 'args' => [$columns]];
        return $this;
    }

    public function whereIn($column, $values, $boolean = 'and', $not = false): static
    {
        $this->calls[] = ['method' => 'whereIn', 'args' => [$column, $values]];
        return $this;
    }

    public function methodNames(): array
    {
        return array_column(
            array_filter($this->calls, fn ($c) => isset($c['method'])),
            'method'
        );
    }

    public function hasCall(string $method): bool
    {
        return in_array($method, $this->methodNames(), true);
    }
}

class TestablePaginatorQueryBool extends PaginatorQuery
{
    public function applyFilterPublic(array $filter): void
    {
        $this->applyFilter($filter);
    }

    public function removeDuplicatesPublic(array $array): array
    {
        return $this->removeDuplicates($array);
    }
}

class PaginatorQueryBoolFilterTest extends TestCase
{
    private function make(string $table = 'items'): array
    {
        $spy = new BoolBuilderSpy($table);
        $paginator = new TestablePaginatorQueryBool($spy);
        return [$spy, $paginator];
    }

    // -----------------------------------------------------------------------
    // and
    // -----------------------------------------------------------------------

    public function testAndCombinatorWrapsEachClauseInWhere(): void
    {
        [$spy, $p] = $this->make();
        $p->applyFilterPublic([
            'and' => [
                ['name' => ['eq' => 'Alice']],
                ['age'  => ['gte' => 18]],
            ],
        ]);

        // The outer AND should produce a top-level where(closure).
        $this->assertTrue($spy->hasCall('where(closure)'), 'Expected a top-level where(closure) for AND combinator.');
    }

    public function testAndWithSingleClause(): void
    {
        [$spy, $p] = $this->make();
        $p->applyFilterPublic([
            'and' => [
                ['name' => ['eq' => 'Bob']],
            ],
        ]);

        $this->assertTrue($spy->hasCall('where(closure)'));
    }

    // -----------------------------------------------------------------------
    // or
    // -----------------------------------------------------------------------

    public function testOrCombinatorWrapsClausesInOrWhere(): void
    {
        [$spy, $p] = $this->make();
        $p->applyFilterPublic([
            'or' => [
                ['name' => ['eq' => 'Alice']],
                ['name' => ['eq' => 'Bob']],
            ],
        ]);

        // orWhere(closure) should be present for the individual branches.
        $this->assertTrue(
            $spy->hasCall('where(closure)'),
            'Expected a top-level where(closure) for OR combinator.'
        );
    }

    // -----------------------------------------------------------------------
    // not
    // -----------------------------------------------------------------------

    public function testNotCombinatorUsesWhereNot(): void
    {
        [$spy, $p] = $this->make();
        $p->applyFilterPublic([
            'not' => ['name' => ['eq' => 'Alice']],
        ]);

        $this->assertTrue($spy->hasCall('whereNot'), 'Expected whereNot for NOT combinator.');
    }

    // -----------------------------------------------------------------------
    // combined: and + not
    // -----------------------------------------------------------------------

    public function testAndAndNotCanBeCombined(): void
    {
        [$spy, $p] = $this->make();
        $p->applyFilterPublic([
            'and' => [
                ['status' => ['eq' => 'active']],
            ],
            'not' => ['deleted' => ['eq' => 1]],
        ]);

        $this->assertTrue($spy->hasCall('where(closure)'));
        $this->assertTrue($spy->hasCall('whereNot'));
    }

    // -----------------------------------------------------------------------
    // removeDuplicates — deduplicates identical filter entries
    // -----------------------------------------------------------------------

    public function testRemoveDuplicatesStripsIdenticalEntries(): void
    {
        [, $p] = $this->make();

        $input = [
            ['name' => ['eq' => 'Alice']],
            ['name' => ['eq' => 'Alice']],  // duplicate
            ['age'  => ['gte' => 18]],
        ];

        $this->assertCount(2, $p->removeDuplicatesPublic($input));
    }

    public function testRemoveDuplicatesKeepsSingleEntry(): void
    {
        [, $p] = $this->make();

        $input = [['name' => ['eq' => 'Alice']]];
        $this->assertCount(1, $p->removeDuplicatesPublic($input));
    }

    public function testRemoveDuplicatesWithEmptyArray(): void
    {
        [, $p] = $this->make();

        $this->assertSame([], $p->removeDuplicatesPublic([]));
    }
}
