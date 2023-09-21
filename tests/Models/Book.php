<?php

namespace EloquentGraphQL\Tests\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property Author $author
 * @property Reader[] $readers
 */
class Book extends Model
{
    public function author()
    {
        return $this->belongsTo(Author::class);
    }

    public function readers()
    {
        return $this->belongsToMany(Reader::class);
    }
}
