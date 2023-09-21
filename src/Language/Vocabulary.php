<?php

namespace EloquentGraphQL\Language;

interface Vocabulary
{
    public function pluralize(string $word): string;

    public function create(string $word): string;

    public function view(string $word): string;

    public function update(string $word): string;

    public function delete(string $word): string;

    public function all(string $word): string;
}
