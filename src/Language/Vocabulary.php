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

    public function errorUnauthorizedFilter(): string;

    public function errorUnauthorizedFilterProperty(string $property): string;

    public function errorUnauthorizedCreate(): string;

    public function errorUnauthorizedDelete(): string;

    public function errorUnauthorizedUpdate(): string;

    public function errorUnauthorizedView(): string;

    public function errorUnauthorizedViewProperty(): string;

    public function errorUnauthorizedViewAny(): string;

}
