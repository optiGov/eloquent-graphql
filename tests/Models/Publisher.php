<?php

namespace EloquentGraphQL\Tests\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Book[] $books
 * @property Author[] $authors
 */
class Publisher extends Model
{
    public function books()
    {
        return $this->hasManyThrough(Book::class, Author::class);
    }

    public function authors()
    {
        return $this->hasMany(Author::class);
    }
}
