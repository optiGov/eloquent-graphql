<?php

namespace EloquentGraphQL\Tests\Pagination;

use EloquentGraphQL\Factories\Pagination\PaginatorQuery;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\TestCase;

/**
 * Test double that exposes the protected applyFilter for direct testing,
 * skipping the EloquentGraphQLService dependency of verifyFilter().
 */
class TestablePaginatorQuery extends PaginatorQuery
{
    public function applyFilterPublic(array $filter): void
    {
        $this->applyFilter($filter);
    }
}

/**
 * Lightweight spy for the Eloquent Builder.
 *
 * Instead of fighting with PHPUnit's onlyMethods/addMethods split (which
 * depends on the exact Laravel version), we extend the real Eloquent Builder,
 * override every method we care about to record the call and return $this,
 * and leave getModel() wired to a real Model stub.
 */
class BuilderSpy extends EloquentBuilder
{
    public array $calls = [];

    private Model $modelStub;

    public function __construct(string $table = 'items')
    {
        // Skip the parent constructor — we don't need a real query grammar.
        $this->modelStub = $this->buildModelStub($table);
    }

    private function buildModelStub(string $table): Model
    {
        $model = new class($table) extends Model {
            private string $testTable;

            public function __construct(string $table)
            {
                $this->testTable = $table;
            }

            public function getTable(): string
            {
                return $this->testTable;
            }
        };

        return $model;
    }

    public function getModel(): Model
    {
        return $this->modelStub;
    }

    // Record all calls below and return $this for fluent chaining.

    public function where($column, $operator = null, $value = null, $boolean = 'and'): static
    {
        $this->calls[] = ['method' => 'where', 'args' => [$column, $operator, $value]];
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

    public function whereNotIn($column, $values, $boolean = 'and'): static
    {
        $this->calls[] = ['method' => 'whereNotIn', 'args' => [$column, $values]];
        return $this;
    }

    public function whereDate($column, $operator, $value = null, $boolean = 'and'): static
    {
        // Normalize two-arg form (operator is actually the value) to three-arg form.
        if ($value === null) {
            $this->calls[] = ['method' => 'whereDate', 'args' => [$column, $operator]];
        } else {
            $this->calls[] = ['method' => 'whereDate', 'args' => [$column, $operator, $value]];
        }
        return $this;
    }

    /** Find the first recorded call for the given method, or null. */
    public function firstCallTo(string $method): ?array
    {
        foreach ($this->calls as $call) {
            if ($call['method'] === $method) {
                return $call;
            }
        }
        return null;
    }

    /** Assert that a method was called, returns the recorded call. */
    public function assertCalled(string $method): array
    {
        $call = $this->firstCallTo($method);
        if ($call === null) {
            throw new \RuntimeException("Expected '$method' to be called, but it was not.");
        }
        return $call;
    }

    /** Assert that a method was NOT called. */
    public function assertNotCalled(string $method): void
    {
        if ($this->firstCallTo($method) !== null) {
            throw new \RuntimeException("Expected '$method' NOT to be called, but it was.");
        }
    }
}

/**
 * Unit tests for the filter-application logic in PaginatorQuery.
 *
 * We use BuilderSpy so no database connection is required.
 */
class PaginatorQueryFilterTest extends TestCase
{
    private function makeSpy(string $table = 'items'): BuilderSpy
    {
        return new BuilderSpy($table);
    }

    private function makePaginator(BuilderSpy $spy): TestablePaginatorQuery
    {
        return new TestablePaginatorQuery($spy);
    }

    // -----------------------------------------------------------------------
    // eq
    // -----------------------------------------------------------------------

    public function testEqWithValueUsesWhereEquals(): void
    {
        $spy = $this->makeSpy();
        $this->makePaginator($spy)->applyFilterPublic(['name' => ['eq' => 'Alice']]);

        $call = $spy->assertCalled('where');
        $this->assertSame('items.name', $call['args'][0]);
        $this->assertSame('=', $call['args'][1]);
        $this->assertSame('Alice', $call['args'][2]);
    }

    public function testEqWithNullUsesWhereNull(): void
    {
        $spy = $this->makeSpy();
        $this->makePaginator($spy)->applyFilterPublic(['name' => ['eq' => null]]);

        $call = $spy->assertCalled('whereNull');
        $this->assertSame('items.name', $call['args'][0]);
        $spy->assertNotCalled('where');
    }

    // -----------------------------------------------------------------------
    // ne
    // -----------------------------------------------------------------------

    public function testNeWithValueUsesWhereNotEquals(): void
    {
        $spy = $this->makeSpy();
        $this->makePaginator($spy)->applyFilterPublic(['name' => ['ne' => 'Bob']]);

        $call = $spy->assertCalled('where');
        $this->assertSame('items.name', $call['args'][0]);
        $this->assertSame('!=', $call['args'][1]);
        $this->assertSame('Bob', $call['args'][2]);
    }

    /**
     * Before the fix, `ne: null` would generate `WHERE field != NULL` which
     * always evaluates to false in SQL.  After the fix it must use whereNotNull.
     */
    public function testNeWithNullUsesWhereNotNull(): void
    {
        $spy = $this->makeSpy();
        $this->makePaginator($spy)->applyFilterPublic(['name' => ['ne' => null]]);

        $call = $spy->assertCalled('whereNotNull');
        $this->assertSame('items.name', $call['args'][0]);
        $spy->assertNotCalled('where');
    }

    // -----------------------------------------------------------------------
    // Comparison operators
    // -----------------------------------------------------------------------

    /** @dataProvider comparisonOperatorProvider */
    public function testComparisonOperatorsForwardToWhere(string $operator, string $sqlOp): void
    {
        $spy = $this->makeSpy();
        $this->makePaginator($spy)->applyFilterPublic(['age' => [$operator => 18]]);

        $call = $spy->assertCalled('where');
        $this->assertSame('items.age', $call['args'][0]);
        $this->assertSame($sqlOp, $call['args'][1]);
        $this->assertSame(18, $call['args'][2]);
    }

    public static function comparisonOperatorProvider(): array
    {
        return [
            'lt'    => ['lt',    '<'],
            'gt'    => ['gt',    '>'],
            'lte'   => ['lte',   '<='],
            'gte'   => ['gte',   '>='],
            'like'  => ['like',  'like'],
            'nlike' => ['nlike', 'not like'],
        ];
    }

    // -----------------------------------------------------------------------
    // in / nin
    // -----------------------------------------------------------------------

    public function testInOperatorUsesWhereIn(): void
    {
        $spy = $this->makeSpy();
        $this->makePaginator($spy)->applyFilterPublic(['status' => ['in' => ['active', 'pending']]]);

        $call = $spy->assertCalled('whereIn');
        $this->assertSame('items.status', $call['args'][0]);
        $this->assertSame(['active', 'pending'], $call['args'][1]);
    }

    public function testNinOperatorUsesWhereNotIn(): void
    {
        $spy = $this->makeSpy();
        $this->makePaginator($spy)->applyFilterPublic(['status' => ['nin' => ['deleted']]]);

        $call = $spy->assertCalled('whereNotIn');
        $this->assertSame('items.status', $call['args'][0]);
        $this->assertSame(['deleted'], $call['args'][1]);
    }

    // -----------------------------------------------------------------------
    // date / ndate
    // -----------------------------------------------------------------------

    public function testDateOperatorUsesWhereDate(): void
    {
        $spy = $this->makeSpy();
        $this->makePaginator($spy)->applyFilterPublic(['created_at' => ['date' => '2024-01-01']]);

        $call = $spy->assertCalled('whereDate');
        $this->assertSame('items.created_at', $call['args'][0]);
        $this->assertSame('2024-01-01', $call['args'][1]);
    }

    public function testNdateOperatorUsesWhereDateNotEquals(): void
    {
        $spy = $this->makeSpy();
        $this->makePaginator($spy)->applyFilterPublic(['created_at' => ['ndate' => '2024-01-01']]);

        $call = $spy->assertCalled('whereDate');
        $this->assertSame('items.created_at', $call['args'][0]);
        $this->assertSame('!=', $call['args'][1]);
        $this->assertSame('2024-01-01', $call['args'][2]);
    }
}
