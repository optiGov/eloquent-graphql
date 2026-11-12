<?php

namespace EloquentGraphQL\Security;

use Closure;
use Illuminate\Contracts\Database\Eloquent\Builder;

/**
 * Describes which models of a class a user can view: all of them, none of them, or those matching a query constraint.
 */
final readonly class Visibility
{
    private function __construct(private bool $isAll, private ?Closure $constraint) {}

    public static function all(): self
    {
        return new self(true, null);
    }

    public static function none(): self
    {
        return new self(false, null);
    }

    public static function allIf(bool $condition): self
    {
        return $condition ? self::all() : self::none();
    }

    /**
     * The constraint is always nested, so that an orWhere() cannot widen the surrounding query, and a constraint
     * that adds no condition matches nothing instead of everything.
     *
     * @param  Closure(Builder): mixed  $constraint
     */
    public static function where(Closure $constraint): self
    {
        return new self(false, fn (Builder $query) => $query->where(function (Builder $query) use ($constraint) {
            $constraint($query);

            if ($query->getQuery()->wheres === []) {
                $query->whereRaw('1 = 0');
            }
        }));
    }

    public function isAll(): bool
    {
        return $this->isAll;
    }

    public function isNone(): bool
    {
        return ! $this->isAll && $this->constraint === null;
    }

    public function getConstraint(): ?Closure
    {
        return $this->constraint;
    }
}
