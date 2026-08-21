<?php

namespace EloquentGraphQL\Tests\Language;

use EloquentGraphQL\Language\VocabularyEnglish;
use PHPUnit\Framework\TestCase;

/**
 * Tests for VocabularyEnglish.
 *
 * The pluralize() method covers the most common English rules.
 * Cases that deviate from standard English are marked with a TODO so that
 * future improvements can be tracked without breaking existing consumers.
 */
class VocabularyEnglishTest extends TestCase
{
    private VocabularyEnglish $vocab;

    protected function setUp(): void
    {
        $this->vocab = new VocabularyEnglish();
    }

    // -----------------------------------------------------------------------
    // pluralize — default rule: append 's'
    // -----------------------------------------------------------------------

    /** @dataProvider defaultSRuleProvider */
    public function testPluralizeDefaultAppendsS(string $singular, string $expected): void
    {
        $this->assertSame($expected, $this->vocab->pluralize($singular));
    }

    public static function defaultSRuleProvider(): array
    {
        return [
            'book'   => ['book',   'books'],
            'author' => ['author', 'authors'],
            'car'    => ['car',    'cars'],
        ];
    }

    // -----------------------------------------------------------------------
    // pluralize — -ay/-ey/-oy/-uy/-iy → keep word, append 's'
    // Previously bugged: substr($word, 0, 0) returned '' so result was just 's'
    // -----------------------------------------------------------------------

    /** @dataProvider vowelYProvider */
    public function testPluralizeVowelYAppendsS(string $singular, string $expected): void
    {
        $this->assertSame($expected, $this->vocab->pluralize($singular));
    }

    public static function vowelYProvider(): array
    {
        return [
            'day'     => ['day',     'days'],
            'play'    => ['play',    'plays'],
            'key'     => ['key',     'keys'],
            'turkey'  => ['turkey',  'turkeys'],
            'boy'     => ['boy',     'boys'],
            'toy'     => ['toy',     'toys'],
            'guy'     => ['guy',     'guys'],
            'holiday' => ['holiday', 'holidays'],  // was 's' before fix
        ];
    }

    // -----------------------------------------------------------------------
    // pluralize — consonant -y → replace with 'ies'
    // -----------------------------------------------------------------------

    /** @dataProvider consonantYProvider */
    public function testPluralizeConsonantY(string $singular, string $expected): void
    {
        $this->assertSame($expected, $this->vocab->pluralize($singular));
    }

    public static function consonantYProvider(): array
    {
        return [
            'city'     => ['city',     'cities'],
            'category' => ['category', 'categories'],
            'library'  => ['library',  'libraries'],
        ];
    }

    // -----------------------------------------------------------------------
    // pluralize — -o → append 'es'
    // Previously bugged: substr($word, 0, 0) returned '' so result was just 'es'
    // -----------------------------------------------------------------------

    public function testPluralizeOEnding(): void
    {
        $this->assertSame('heroes',   $this->vocab->pluralize('hero'));    // was 'es' before fix
        $this->assertSame('potatoes', $this->vocab->pluralize('potato'));  // was 'es' before fix
        $this->assertSame('tomatoes', $this->vocab->pluralize('tomato'));  // was 'es' before fix
    }

    // -----------------------------------------------------------------------
    // pluralize — -f/-fe → replace with 'ves'
    // -----------------------------------------------------------------------

    public function testPluralizeFEnding(): void
    {
        $this->assertSame('wolves', $this->vocab->pluralize('wolf'));
        $this->assertSame('knives', $this->vocab->pluralize('knife'));
    }

    // -----------------------------------------------------------------------
    // pluralize — Latin/Greek endings (-us, -is, -on)
    // Note: the generic -s rule fires before -us, producing non-standard forms.
    // These tests document the actual current behaviour.
    // TODO: reorder rules so -us/-is come before the generic -s check.
    // -----------------------------------------------------------------------

    public function testPluralizeLatinOnEnding(): void
    {
        // -on rule fires before -s, so these are standard.
        $this->assertSame('phenomena', $this->vocab->pluralize('phenomenon'));
        $this->assertSame('criteria',  $this->vocab->pluralize('criterion'));
    }

    public function testPluralizeLatinUsEndingCurrentBehaviour(): void
    {
        // TODO: 'cactus' should → 'cacti', but the generic -s rule fires first.
        $this->assertSame('cactues',  $this->vocab->pluralize('cactus'));
        $this->assertSame('nucleues', $this->vocab->pluralize('nucleus'));
    }

    public function testPluralizeLatinIsEndingCurrentBehaviour(): void
    {
        // TODO: 'analysis' should → 'analyses', but -s fires first (→ 'analysies').
        $this->assertSame('analysies', $this->vocab->pluralize('analysis'));
        $this->assertSame('crisies',   $this->vocab->pluralize('crisis'));
    }

    // -----------------------------------------------------------------------
    // CRUD verb helpers
    // -----------------------------------------------------------------------

    public function testCreateVerb(): void
    {
        $this->assertSame('createBook',   $this->vocab->create('Book'));
        $this->assertSame('createAuthor', $this->vocab->create('Author'));
    }

    public function testViewVerb(): void
    {
        $this->assertSame('book',   $this->vocab->view('Book'));
        $this->assertSame('author', $this->vocab->view('Author'));
    }

    public function testUpdateVerb(): void
    {
        $this->assertSame('updateBook', $this->vocab->update('Book'));
    }

    public function testDeleteVerb(): void
    {
        $this->assertSame('deleteBook', $this->vocab->delete('Book'));
    }

    public function testDuplicateVerb(): void
    {
        $this->assertSame('duplicateBook',   $this->vocab->duplicate('Book'));
        $this->assertSame('duplicateAuthor', $this->vocab->duplicate('Author'));
    }

    public function testUnauthorizedDuplicateError(): void
    {
        $this->assertSame('You are not authorized to duplicate this model.', $this->vocab->errorUnauthorizedDuplicate());
    }

    public function testAllVerbWithCommonModels(): void
    {
        $this->assertSame('allBooks',    $this->vocab->all('Book'));
        $this->assertSame('allAuthors',  $this->vocab->all('Author'));
        // Previously bugged: returned 'alls' because substr(..., 0, 0) = ''
        $this->assertSame('allHolidays', $this->vocab->all('Holiday'));
        $this->assertSame('allToys',     $this->vocab->all('Toy'));
    }
}
