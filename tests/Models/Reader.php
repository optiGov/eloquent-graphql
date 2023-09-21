<?php

namespace EloquentGraphQL\Tests\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property Book[] $books
 */
class Reader extends Model
{
    public function books()
    {
        return $this->belongsToMany(Book::class);
    }
}
