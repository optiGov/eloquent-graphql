<?php

namespace EloquentGraphQL\Language;

class VocabularyGerman implements Vocabulary
{
    /**
     * Holds all exceptions with a custom plural form.
     */
    private array $exceptions = [
        'job' => 'jobs',
        'login' => 'logins',
        'konto' => 'konten',
        'pizza' => 'pizzen',
        'kaktus' => 'kakteen',
    ];

    public function __construct(array $exceptions = [])
    {
        $this->exceptions = array_merge($this->exceptions, $exceptions);
    }

    public function pluralize(string $word): string
    {
        if (array_key_exists($word, $this->exceptions)) {
            return $this->exceptions[$word];
        }

        if (str_ends_with($word, 'e')) {
            return $word.'n';
        }
        if (str_ends_with($word, 'ent')) {
            return $word.'en';
        }
        if (str_ends_with($word, 'and')) {
            return $word.'en';
        }
        if (str_ends_with($word, 'ant')) {
            return $word.'en';
        }
        if (str_ends_with($word, 'ist')) {
            return $word.'en';
        }
        if (str_ends_with($word, 'or')) {
            return $word.'en';
        }
        if (str_ends_with($word, 'in')) {
            return $word.'en';
        }
        if (str_ends_with($word, 'ion')) {
            return $word.'en';
        }
        if (str_ends_with($word, 'ik')) {
            return $word.'en';
        }
        if (str_ends_with($word, 'heit')) {
            return $word.'en';
        }
        if (str_ends_with($word, 'keit')) {
            return $word.'en';
        }
        if (str_ends_with($word, 'schaft')) {
            return $word.'en';
        }
        if (str_ends_with($word, 'tät')) {
            return $word.'en';
        }
        if (str_ends_with($word, 'ung')) {
            return $word.'en';
        }
        if (str_ends_with($word, 'ma')) {
            return substr($word, 0, -1).'en';
        }
        if (str_ends_with($word, 'um')) {
            return substr($word, 0, -2).'en';
        }
        if (str_ends_with($word, 'us')) {
            return $word.'en';
        }
        if (str_ends_with($word, 'eur')) {
            return $word.'e';
        }
        if (str_ends_with($word, 'ich')) {
            return $word.'e';
        }
        if (str_ends_with($word, 'ier')) {
            return $word.'e';
        }
        if (str_ends_with($word, 'iet')) {
            return $word.'e';
        }
        if (str_ends_with($word, 'ig')) {
            return $word.'e';
        }
        if (str_ends_with($word, 'ling')) {
            return $word.'e';
        }
        if (str_ends_with($word, 'ör')) {
            return $word.'e';
        }
        if (str_ends_with($word, 'nd')) {
            return $word.'e';
        }
        if (str_ends_with($word, 'a')) {
            return $word.'s';
        }
        if (str_ends_with($word, 'i')) {
            return $word.'s';
        }
        if (str_ends_with($word, 'o')) {
            return $word.'s';
        }
        if (str_ends_with($word, 'u')) {
            return $word.'s';
        }
        if (str_ends_with($word, 'y')) {
            return $word.'s';
        }
        if (str_ends_with($word, 'aub')) {
            return substr($word, 0, -3).'aeube';
        }
        if (str_ends_with($word, 'ub')) {
            return substr($word, 0, -2).'uebe';
        }
        if (str_ends_with($word, 'ob')) {
            return substr($word, 0, -2).'oebe';
        }
        if (str_ends_with($word, 'ab')) {
            return substr($word, 0, -2).'aebe';
        }
        if (str_ends_with($word, 'eb')) {
            return substr($word, 0, -2).'ebe';
        }

        return $word;
    }

    public function create(string $word): string
    {
        return 'erstelle'.ucwords($word);
    }

    public function view(string $word): string
    {
        return lcfirst($word);
    }

    public function update(string $word): string
    {
        return 'bearbeite'.ucwords($word);
    }

    public function delete(string $word): string
    {
        return 'loesche'.ucwords($word);
    }

    public function all(string $word): string
    {
        return 'alle'.ucwords($this->pluralize($word));
    }

    public function errorUnauthorizedFilter(): string
    {
        return 'Sie sind nicht berechtigt, diesen Filter zu verwenden.';
    }

    public function errorUnauthorizedFilterProperty(string $property): string
    {
        return 'Sie sind nicht berechtigt, die Eigenschaft ['.$property.'] zu filtern.';
    }

    public function errorUnauthorizedCreate(): string
    {
        return 'Sie sind nicht berechtigt, diesen Eintrag zu erstellen.';
    }

    public function errorUnauthorizedDelete(): string
    {
        return 'Sie sind nicht berechtigt, diesen Eintrag zu löschen.';
    }

    public function errorUnauthorizedUpdate(): string
    {
        return 'Sie sind nicht berechtigt, diesen Eintrag zu bearbeiten.';
    }

    public function errorUnauthorizedView(): string
    {
        return 'Sie sind nicht berechtigt, diesen Eintrag anzusehen.';
    }

    public function errorUnauthorizedViewProperty(): string
    {
        return 'Sie sind nicht berechtigt, diese Eigenschaft anzusehen.';
    }

    public function errorUnauthorizedViewAny(): string
    {
        return 'Sie sind nicht berechtigt, diese Einträge anzusehen.';
    }
}
