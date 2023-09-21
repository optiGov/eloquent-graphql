<?php

namespace EloquentGraphQL\Tests\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $name
 * @property Author $author
 */
class Pencil extends Model
{
    public function author()
    {
        return $this->belongsTo(Author::class);
    }
}
