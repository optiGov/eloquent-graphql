<?php

namespace EloquentGraphQL\Tests\Language;

use EloquentGraphQL\Language\VocabularyGerman;
use PHPUnit\Framework\TestCase;

class VocabularyGermanTest extends TestCase
{
    private VocabularyGerman $vocab;

    protected function setUp(): void
    {
        $this->vocab = new VocabularyGerman();
    }

    // -----------------------------------------------------------------------
    // pluralize — built-in exceptions
    // -----------------------------------------------------------------------

    /** @dataProvider builtInExceptionProvider */
    public function testBuiltInExceptions(string $singular, string $expected): void
    {
        $this->assertSame($expected, $this->vocab->pluralize($singular));
    }

    public static function builtInExceptionProvider(): array
    {
        return [
            'job'    => ['job',    'jobs'],
            'login'  => ['login',  'logins'],
            'konto'  => ['konto',  'konten'],
            'pizza'  => ['pizza',  'pizzen'],
            'kaktus' => ['kaktus', 'kakteen'],
        ];
    }

    public function testCustomExceptionsExtendDefaults(): void
    {
        $vocab = new VocabularyGerman(['auto' => 'autos', 'konto' => 'kontos']);
        $this->assertSame('autos',  $vocab->pluralize('auto'));
        // Custom exception overrides the built-in rule.
        $this->assertSame('kontos', $vocab->pluralize('konto'));
    }

    // -----------------------------------------------------------------------
    // pluralize — suffix rules (append -en / -n / -e / -s)
    // -----------------------------------------------------------------------

    /** @dataProvider suffixRuleProvider */
    public function testSuffixRules(string $singular, string $expected): void
    {
        $this->assertSame($expected, $this->vocab->pluralize($singular));
    }

    public static function suffixRuleProvider(): array
    {
        return [
            // -e → append 'n'
            'katze'        => ['katze',        'katzen'],
            // -ent / -and / -ant / -ist / -or / -in / -ion / -ik / -heit / -keit / -schaft / -ung → append 'en'
            'student'      => ['student',      'studenten'],
            'doktorand'    => ['doktorand',    'doktoranden'],
            'demonstrant'  => ['demonstrant',  'demonstranten'],
            'journalist'   => ['journalist',   'journalisten'],
            'faktor'       => ['faktor',       'faktoren'],
            'freundin'     => ['freundin',     'freundinen'],   // -in + en (not -innen)
            'nation'       => ['nation',       'nationen'],
            'musik'        => ['musik',        'musiken'],
            'freiheit'     => ['freiheit',     'freiheiten'],
            'einigkeit'    => ['einigkeit',    'einigkeiten'],
            'gemeinschaft' => ['gemeinschaft', 'gemeinschaften'],
            'meinung'      => ['meinung',      'meinungen'],
            // -um → strip -um, append 'en'
            'datum'        => ['datum',        'daten'],
            // -ma → strip -a, append 'en'
            'thema'        => ['thema',        'themen'],
            // -eur / -ich / -ig / -ling / -nd → append 'e'
            'ateur'        => ['ateur',        'ateure'],
            'teppich'      => ['teppich',      'teppiche'],
            'könig'        => ['könig',        'könige'],
            'lehrling'     => ['lehrling',     'lehrlinge'],
            'hund'         => ['hund',         'hunde'],
            // -a / -i / -o / -y → append 's'
            'sofa'         => ['sofa',         'sofas'],
            'kiwi'         => ['kiwi',         'kiwis'],
            'video'        => ['video',        'videos'],
            'hobby'        => ['hobby',        'hobbys'],
        ];
    }

    // -----------------------------------------------------------------------
    // pluralize — fallback: return word unchanged
    // -----------------------------------------------------------------------

    public function testFallbackReturnsWordUnchanged(): void
    {
        // 'tisch' ends in '-ch' but NOT '-ich', so no rule matches.
        $this->assertSame('tisch', $this->vocab->pluralize('tisch'));
    }

    // -----------------------------------------------------------------------
    // CRUD verb helpers
    // -----------------------------------------------------------------------

    public function testCreateVerb(): void
    {
        $this->assertSame('erstelleBenutzer', $this->vocab->create('Benutzer'));
    }

    public function testViewVerb(): void
    {
        $this->assertSame('benutzer', $this->vocab->view('Benutzer'));
    }

    public function testUpdateVerb(): void
    {
        $this->assertSame('bearbeiteBenutzer', $this->vocab->update('Benutzer'));
    }

    public function testDeleteVerb(): void
    {
        $this->assertSame('loescheBenutzer', $this->vocab->delete('Benutzer'));
    }

    public function testAllVerb(): void
    {
        $this->assertSame('alleMeinungen',   $this->vocab->all('Meinung'));
        $this->assertSame('alleStudenten',   $this->vocab->all('Student'));
        $this->assertSame('alleKategorien',  $this->vocab->all('Kategorie'));
    }
}
