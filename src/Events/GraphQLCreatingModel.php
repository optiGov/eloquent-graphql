<?php

namespace EloquentGraphQL\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class GraphQLCreatingModel
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     */
    public function __construct(private Model $model)
    {
        //
    }

    public function getModel(): Model
    {
        return $this->model;
    }
}