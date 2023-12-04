<?php

namespace EloquentGraphQL\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Book[] $books @paginate @filter
 * @property Publisher $publisher
 */
class Author extends Model
{
    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }

    public function publisher()
    {
        return $this->belongsTo(Publisher::class);
    }

    public function pencil()
    {
        return $this->hasOne(Pencil::class);
    }
}
