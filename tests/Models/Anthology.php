<?php

namespace EloquentGraphQL\Tests\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Relates to authors in every way, so that changes of relations to models the user cannot view can be tested.
 *
 * @property int $id
 * @property string $name
 * @property Author[] $authors
 * @property Author[] $members
 * @property ?Author $editor
 * @property ?Author $chief
 */
class Anthology extends Model
{
    public function authors(): BelongsToMany
    {
        return $this->belongsToMany(Author::class);
    }

    public function members(): HasMany
    {
        return $this->hasMany(Author::class);
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(Author::class);
    }

    public function chief(): HasOne
    {
        return $this->hasOne(Author::class, 'chief_of_anthology_id');
    }
}
