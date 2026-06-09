<?php

namespace EloquentGraphQL\Tests\Models;

use EloquentGraphQL\Tests\Enums\Priority;
use EloquentGraphQL\Tests\Enums\Status;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $title
 * @property Status $status @filterable @orderable
 * @property ?Priority $priority @filterable
 */
class Article extends Model
{
}
