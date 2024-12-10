<?php

namespace EloquentGraphQL\Language;

class VocabularyEnglish implements Vocabulary
{
    public function pluralize(string $word): string
    {
        if (str_ends_with($word, 's')) {
            return substr($word, 0, -1).'es';
        }
        if (str_ends_with($word, 'ss')) {
            return substr($word, 0, -2).'es';
        }
        if (str_ends_with($word, 'sh')) {
            return substr($word, 0, -2).'es';
        }
        if (str_ends_with($word, 'ch')) {
            return substr($word, 0, -2).'es';
        }
        if (str_ends_with($word, 'x')) {
            return substr($word, 0, -1).'es';
        }
        if (str_ends_with($word, 'z')) {
            return substr($word, 0, -1).'es';
        }
        if (str_ends_with($word, 'f')) {
            return substr($word, 0, -1).'ves';
        }
        if (str_ends_with($word, 'fe')) {
            return substr($word, 0, -2).'ves';
        }
        if (str_ends_with($word, 'ay')) {
            return substr($word, 0, 0).'s';
        }
        if (str_ends_with($word, 'ey')) {
            return substr($word, 0, 0).'s';
        }
        if (str_ends_with($word, 'iy')) {
            return substr($word, 0, 0).'s';
        }
        if (str_ends_with($word, 'oy')) {
            return substr($word, 0, 0).'s';
        }
        if (str_ends_with($word, 'uy')) {
            return substr($word, 0, 0).'s';
        }
        if (str_ends_with($word, 'y')) {
            return substr($word, 0, -1).'ies';
        }
        if (str_ends_with($word, 'o')) {
            return substr($word, 0, 0).'es';
        }
        if (str_ends_with($word, 'us')) {
            return substr($word, 0, -2).'i';
        }
        if (str_ends_with($word, 'is')) {
            return substr($word, 0, -2).'es';
        }
        if (str_ends_with($word, 'on')) {
            return substr($word, 0, -2).'a';
        }

        return $word.'s';
    }

    public function create(string $word): string
    {
        return 'create'.ucwords($word);
    }

    public function view(string $word): string
    {
        return lcfirst($word);
    }

    public function update(string $word): string
    {
        return 'update'.ucwords($word);
    }

    public function delete(string $word): string
    {
        return 'delete'.ucwords($word);
    }

    public function all(string $word): string
    {
        return 'all'.ucwords($this->pluralize($word));
    }

    public function errorUnauthorizedFilter(): string
    {
        return 'You are not authorized to filter this type';
    }

    public function errorUnauthorizedFilterProperty(string $property): string
    {
        return 'You are not authorized to filter the property ['.$property.'].';
    }

    public function errorUnauthorizedCreate(): string
    {
        return 'You are not authorized to create this model.';
    }

    public function errorUnauthorizedDelete(): string
    {
        return 'You are not authorized to delete this model.';
    }

    public function errorUnauthorizedUpdate(): string
    {
        return 'You are not authorized to update this model.';
    }

    public function errorUnauthorizedView(): string
    {
        return 'You are not authorized to view this model.';
    }

    public function errorUnauthorizedViewProperty(): string
    {
        return 'You are not authorized to view this property.';
    }

    public function errorUnauthorizedViewAny(): string
    {
        return 'You are not authorized to view any model.';
    }
}
