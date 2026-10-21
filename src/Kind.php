<?php

namespace ClaudioDekker\LaravelGuardrails;

/**
 * The kind of code a file holds. A file has exactly one kind. A new case can be added in a minor release, so never match on a kind exhaustively without a default arm.
 *
 * @api
 */
enum Kind: string
{
    /**
     * The kinds that are separate from TEXT, which any kind added later is not.
     */
    protected const SEPARATE = [self::CONFIG, self::FRONTEND, self::LANG, self::MIGRATIONS, self::ROUTES, self::SOURCE, self::TESTS, self::VIEWS];

    /**
     * The config files: the config folder, and the one beside each source root's parent.
     */
    case CONFIG = 'config';

    /**
     * The frontend code: resources/js and resources/css, and the ones beside each source root's parent.
     */
    case FRONTEND = 'frontend';

    /**
     * The translations: lang and resources/lang, and the ones beside each source root's parent.
     */
    case LANG = 'lang';

    /**
     * The migrations: database/migrations, and the one beside each source root's parent.
     */
    case MIGRATIONS = 'migrations';

    /**
     * The routes: the routes folder, and the one beside each source root's parent.
     */
    case ROUTES = 'routes';

    /**
     * The code the project autoloads, from the autoload paths of composer.json.
     */
    case SOURCE = 'source';

    /**
     * The tests, from the autoload-dev paths of composer.json and the test suites of phpunit.xml.
     */
    case TESTS = 'tests';

    /**
     * Every file not under another kind, such as the docs and the configuration of other tools. A new kind is only ever carved out of text, and a check that lists TEXT also reads every kind carved out of it later, so it never loses a file to a kind added later.
     */
    case TEXT = 'text';

    /**
     * The views: resources/views, and the one beside each source root's parent.
     */
    case VIEWS = 'views';

    /**
     * Determine if the kind covers the given kind: itself, and for TEXT every kind added after the ones that are separate from it.
     */
    public function includes(self $kind): bool
    {
        return $this === $kind || ($this === self::TEXT && ! in_array($kind, self::SEPARATE, true));
    }

    /**
     * Get every kind but the given ones.
     *
     * @return list<self>
     */
    public static function except(self ...$kinds): array
    {
        return array_values(array_filter(self::cases(), fn (self $kind) => ! in_array($kind, $kinds, true)));
    }

    /**
     * Get the words that tell the given kinds from every kind: nothing for all of them, else "in" the few or "outside" the few.
     *
     * @param  list<self>  $kinds
     */
    public static function describe(array $kinds): string
    {
        $missing = self::except(...$kinds);

        if ($missing === []) {
            return '';
        }

        if (count($missing) < count($kinds)) {
            return 'outside '.self::sentence($missing);
        }

        return 'in '.self::sentence($kinds);
    }

    /**
     * Get the kind's label.
     */
    public function label(): string
    {
        return match ($this) {
            self::CONFIG => 'config',
            self::FRONTEND => 'frontend code',
            self::LANG => 'translations',
            self::MIGRATIONS => 'migrations',
            self::ROUTES => 'routes',
            self::SOURCE => 'source code',
            self::TESTS => 'tests',
            self::TEXT => 'other files',
            self::VIEWS => 'views',
        };
    }

    /**
     * Join the labels of the kinds into a sentence, as "a, b and c".
     *
     * @param  list<self>  $kinds
     */
    protected static function sentence(array $kinds): string
    {
        $labels = array_map(fn (self $kind) => $kind->label(), $kinds);
        $last = array_pop($labels);

        if ($last === null) {
            return '';
        }

        return $labels === [] ? $last : implode(', ', $labels).' and '.$last;
    }
}
