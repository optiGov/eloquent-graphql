<?php

namespace EloquentGraphQL\Tests\Pagination;

use EloquentGraphQL\Exceptions\GraphQLError;
use EloquentGraphQL\Factories\Pagination\PaginatorIterable;
use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;

class PaginatorIterableTest extends TestCase
{
    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makePaginator(): PaginatorIterable
    {
        return new PaginatorIterable();
    }

    private function items(int $count): array
    {
        return range(1, $count);
    }

    // -----------------------------------------------------------------------
    // count()
    // -----------------------------------------------------------------------

    public function testCountReturnsNumberOfEntries(): void
    {
        $p = $this->makePaginator();
        $p->setEntries($this->items(5));

        $this->assertSame(5, $p->count());
    }

    public function testCountWorksWithCollection(): void
    {
        $p = $this->makePaginator();
        $p->setEntries(new Collection($this->items(3)));

        $this->assertSame(3, $p->count());
    }

    // -----------------------------------------------------------------------
    // get() — array input
    // -----------------------------------------------------------------------

    public function testGetReturnsAllItemsWhenNoLimitOrOffset(): void
    {
        $p = $this->makePaginator();
        $p->setEntries([10, 20, 30]);

        $this->assertSame([10, 20, 30], $p->get());
    }

    public function testGetRespectsLimitOnArray(): void
    {
        $p = $this->makePaginator();
        $p->setEntries($this->items(10));
        $p->limit(3);

        $this->assertCount(3, $p->get());
        $this->assertSame([1, 2, 3], $p->get());
    }

    public function testGetRespectsOffsetOnArray(): void
    {
        $p = $this->makePaginator();
        $p->setEntries([10, 20, 30, 40, 50]);
        $p->offset(2);

        $this->assertSame([30, 40, 50], array_values($p->get()));
    }

    public function testGetRespectsLimitAndOffsetOnArray(): void
    {
        $p = $this->makePaginator();
        $p->setEntries($this->items(10));
        $p->offset(3)->limit(4);

        $this->assertSame([4, 5, 6, 7], array_values($p->get()));
    }

    public function testGetWithOffsetBeyondEndReturnsEmpty(): void
    {
        $p = $this->makePaginator();
        $p->setEntries($this->items(3));
        $p->offset(10);

        $this->assertSame([], $p->get());
    }

    public function testGetWithLimitLargerThanItemsReturnsAll(): void
    {
        $p = $this->makePaginator();
        $p->setEntries([1, 2]);
        $p->limit(100);

        $this->assertSame([1, 2], $p->get());
    }

    // -----------------------------------------------------------------------
    // get() — Collection input
    // -----------------------------------------------------------------------

    public function testGetReturnsAllCollectionItemsWhenNoLimitOrOffset(): void
    {
        $p = $this->makePaginator();
        $p->setEntries(new Collection([10, 20, 30]));

        $this->assertSame([10, 20, 30], array_values($p->get()));
    }

    public function testGetRespectsLimitOnCollection(): void
    {
        $p = $this->makePaginator();
        $p->setEntries(new Collection($this->items(10)));
        $p->limit(3);

        $result = $p->get();
        $this->assertCount(3, $result);
        $this->assertSame([1, 2, 3], array_values($result));
    }

    public function testGetRespectsLimitAndOffsetOnCollection(): void
    {
        $p = $this->makePaginator();
        $p->setEntries(new Collection($this->items(10)));
        $p->offset(4)->limit(3);

        $this->assertSame([5, 6, 7], array_values($p->get()));
    }

    // -----------------------------------------------------------------------
    // get() — unsupported type throws Exception
    // -----------------------------------------------------------------------

    public function testGetThrowsForUnsupportedEntryType(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Unsupported iterable type.');

        $p = $this->makePaginator();
        $p->setEntries('not-a-collection');
        $p->get();
    }

    // -----------------------------------------------------------------------
    // filter/order not supported → GraphQLError
    // -----------------------------------------------------------------------

    public function testFilterThrowsGraphQLError(): void
    {
        $this->expectException(GraphQLError::class);

        // PaginatorIterable inherits Paginator::applyFilter which throws.
        // We call it via a thin subclass wrapper to avoid reflection.
        $p = new class extends PaginatorIterable {
            public function triggerFilter(array $f): void { $this->applyFilter($f); }
        };
        $p->setEntries([]);
        $p->triggerFilter(['name' => ['eq' => 'test']]);
    }

    public function testOrderThrowsGraphQLError(): void
    {
        $this->expectException(GraphQLError::class);

        $p = new class extends PaginatorIterable {
            public function triggerOrder(array $o): void { $this->applyOrder($o); }
        };
        $p->setEntries([]);
        $p->triggerOrder(['name' => ['order' => 'asc']]);
    }

    // -----------------------------------------------------------------------
    // noLimit / noOffset helpers
    // -----------------------------------------------------------------------

    public function testNoLimitResetsLimit(): void
    {
        $p = $this->makePaginator();
        $p->setEntries($this->items(5));
        $p->limit(2)->noLimit();

        $this->assertCount(5, $p->get());
    }

    public function testNoOffsetResetsOffset(): void
    {
        $p = $this->makePaginator();
        $p->setEntries([10, 20, 30]);
        $p->offset(2)->noOffset();

        $this->assertSame([10, 20, 30], $p->get());
    }
}
