<?php

namespace ClaudioDekker\LaravelGuardrails;

use ClaudioDekker\LaravelGuardrails\Prompts\TreePrompt;
use InvalidArgumentException;
use RuntimeException;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\note;
use function Laravel\Prompts\outro;
use function Laravel\Prompts\warning;

/**
 * @internal
 */
class InstallCommand
{
    /**
     * The usage line printed with an unknown argument or --help.
     */
    protected const USAGE = 'Usage: guardrails install [--preset=<name>]... [--check=<class>]... [--help]';

    /**
     * The line printed when there is no terminal to ask in and no flags to go by.
     */
    protected const NO_TERMINAL = 'No terminal to ask in. Pass --preset=<name> or --check=<class>, or both, to choose the checks without prompts.';

    /**
     * The key of the option that starts from the current selection.
     */
    protected const CURRENT = 'current';

    /**
     * The number of rows a menu or the tree shows at once.
     */
    protected const SCROLL = 10;

    /**
     * Create the command for the project in the given directory, offering the checks of the discovered packs.
     *
     * @param  list<class-string<Check>>  $offered
     */
    protected function __construct(
        protected readonly string $directory,
        protected readonly Configuration $configuration,
        protected readonly ConfigurationFile $file,
        protected readonly Catalog $catalog,
        protected readonly array $offered,
    ) {
        //
    }

    /**
     * Choose the checks of the project in the given directory, by the flags or by asking, write them to guardrails.php and install the hook.
     *
     * @param  list<string>  $arguments
     * @param  resource  $output
     * @param  resource  $errorOutput
     */
    public static function run(string $directory, array $arguments, $output, $errorOutput, ?bool $interactive = null): int
    {
        if (in_array('--help', $arguments, true)) {
            fwrite($output, static::USAGE."\n");

            return 0;
        }

        $presets = [];
        $checks = [];

        foreach ($arguments as $argument) {
            if ($argument === '--preset=' || $argument === '--check=') {
                fwrite($errorOutput, rtrim($argument, '=').' needs a value, such as '.($argument === '--preset=' ? '--preset=<name>' : '--check=<class>').'. '.static::USAGE."\n");

                return 2;
            }

            if (str_starts_with($argument, '--preset=')) {
                $presets[] = substr($argument, strlen('--preset='));
            } elseif (str_starts_with($argument, '--check=')) {
                $checks[] = substr($argument, strlen('--check='));
            } else {
                fwrite($errorOutput, "Unknown argument {$argument}. ".static::USAGE."\n");

                return 2;
            }
        }

        $interactive ??= stream_isatty(STDIN);

        try {
            $configuration = static::configuration($directory, $errorOutput);
            $file = ConfigurationFile::read($directory);
            $catalog = Catalog::discover($directory);
            $command = new self($directory, $configuration, $file, $catalog, static::offered($configuration->discoverable($catalog), $interactive));

            if ($presets !== [] || $checks !== []) {
                $selection = $command->scripted($presets, $checks);
            } elseif ($interactive) {
                $selection = $command->ask();
            } else {
                fwrite($errorOutput, static::NO_TERMINAL."\n");

                return 2;
            }

            if ($selection === null) {
                $command->say(static::installHook($directory), $interactive, $output);

                return 0;
            }

            $file->select($selection);
        } catch (InvalidArgumentException $exception) {
            fwrite($errorOutput, $exception->getMessage()."\n");

            return 2;
        }

        if ($presets !== [] || $checks !== []) {
            $command->say($command->added(array_values(array_diff($selection, $file->classes()))), $interactive, $output);
        }

        $command->say('Selected '.Command::count(count($selection), 'check').' in guardrails.php.', $interactive, $output);
        $command->say(static::installHook($directory), $interactive, $output);

        if ($interactive) {
            outro('Run vendor/bin/guardrails install again to change the selection.');
        }

        return 0;
    }

    /**
     * Read the configuration of the project, or only what its composer.json holds when its guardrails.php fails to load, saying why, so a broken entry can still be deselected.
     *
     * @param  resource  $errorOutput
     *
     * @throws InvalidArgumentException
     */
    protected static function configuration(string $directory, $errorOutput): Configuration
    {
        try {
            return Configuration::fromProject($directory);
        } catch (InvalidArgumentException $exception) {
            $configuration = Configuration::fromComposerJson($directory);

            fwrite($errorOutput, $exception->getMessage()."\n");

            return $configuration;
        }
    }

    /**
     * Get the checks the catalog provides that exist, warning about the ones that do not when asked to.
     *
     * @return list<class-string<Check>>
     */
    protected static function offered(Catalog $catalog, bool $warn): array
    {
        $offered = [];

        foreach ($catalog->packs as $pack) {
            $offered = [...$offered, ...$pack->existing()];

            foreach ($warn ? $pack->missing() : [] as $class) {
                warning("{$pack->name} declares {$class}, which does not exist or is not a check, so it is not offered.");
            }
        }

        return array_values(array_unique($offered));
    }

    /**
     * Expand the presets, require each check they and the flags name to be one the project may run, and add them to the entries guardrails.php already selects, which stay as they are.
     *
     * @param  list<string>  $presets
     * @param  list<string>  $checks
     * @return list<string>
     *
     * @throws InvalidArgumentException
     */
    protected function scripted(array $presets, array $checks): array
    {
        $catalog = $this->configuration->discoverable($this->catalog);
        $requested = array_values(array_unique([...array_merge([], ...array_map(fn (string $preset) => $catalog->preset($preset)->checks, $presets)), ...$checks]));

        (new Configuration($requested, $this->configuration->dontDiscover))->checks($this->catalog);

        return $this->ordered([...$this->file->classes(), ...$requested]);
    }

    /**
     * Get the line that names the checks the flags added, or says they added none.
     *
     * @param  list<string>  $added
     */
    protected function added(array $added): string
    {
        return $added === [] ? 'Every check asked for was already selected.' : $this->listed('Added '.Command::count(count($added), 'check').':', $added);
    }

    /**
     * Get the classes of the entries of guardrails.php that no package offers, which the command keeps as they are written.
     *
     * @return list<string>
     */
    protected function unmanaged(): array
    {
        return array_values(array_unique(array_filter($this->file->classes(), fn (string $class) => $this->isUnmanaged($class))));
    }

    /**
     * Determine if the class is a check no package offers, which only its own entry in guardrails.php selects.
     */
    protected function isUnmanaged(string $class): bool
    {
        return $this->catalog->provider($class) === null && is_subclass_of($class, Check::class);
    }

    /**
     * Ask which presets to start from, then which checks to turn on or off in the tree, and get the selection, or null to leave guardrails.php as it is.
     *
     * @return list<string>|null
     */
    protected function ask(): ?array
    {
        intro('Laravel Guardrails');

        $catalog = $this->configuration->discoverable($this->catalog);
        $groups = $catalog->groups();
        [$selection, $left] = $this->start();

        if ($groups !== []) {
            $selection = (new TreePrompt('Choose the checks', $groups, $catalog->presets(), $selection, $this->configured(), static::SCROLL))->prompt();
        }

        return $this->review($selection, $left);
    }

    /**
     * Get the instances of the checks guardrails.php configures as an entry of their own, which the tree marks.
     *
     * @return list<Check>
     */
    protected function configured(): array
    {
        return array_values(array_filter($this->configuration->instances(), fn (Check $check) => in_array($check::class, $this->file->configured(), true)));
    }

    /**
     * Ask which presets to start from, and get the checks that start on, and the ones a preset names that are not offered, which are left out, with the reason for each.
     *
     * @return array{list<string>, array<string, string>}
     */
    protected function start(): array
    {
        $current = $this->file->classes();
        $options = [];
        $presets = $this->configuration->discoverable($this->catalog)->presets();

        if ($current !== []) {
            $options[static::CURRENT] = 'Your current selection, '.count($current).' checks';
        }

        foreach ($presets as $preset) {
            $options[$preset->id()] = $preset->label();
        }

        if ($options === []) {
            return [[], []];
        }

        $started = multiselect('Start from', $options, [$this->preselected($current !== [], $presets)], required: false, scroll: static::SCROLL, hint: 'Space ticks a preset. Tick several to turn on all their checks, or none to start from nothing.');
        $selection = [];
        $left = [];

        foreach ($started as $start) {
            if ($start === static::CURRENT) {
                $selection = [...$selection, ...$current];

                continue;
            }

            foreach ($this->catalog->preset((string) $start)->checks as $class) {
                if (in_array($class, $this->offered, true)) {
                    $selection[] = $class;
                } else {
                    $left[$class] = $this->reason($class);
                }
            }
        }

        return [array_values(array_unique($selection)), $left];
    }

    /**
     * Get why a check that a preset names is not offered.
     */
    protected function reason(string $class): string
    {
        if (! class_exists($class) || ! is_subclass_of($class, Check::class)) {
            return 'it does not exist or is not a check';
        }

        return 'its package is not discovered';
    }

    /**
     * Get the key of the option that starts ticked: the current selection when there is one, else the built-in app preset for a project and the built-in package preset for anything else.
     *
     * @param  list<Preset>  $presets
     */
    protected function preselected(bool $hasSelection, array $presets): string
    {
        if ($hasSelection) {
            return static::CURRENT;
        }

        $type = Catalog::manifest($this->directory.'/composer.json')['type'] ?? null;
        $name = $type === 'project' ? 'app' : 'package';

        foreach ($presets as $preset) {
            if ($preset->pack->isBuiltIn() && $preset->name === $name) {
                return $preset->id();
            }
        }

        return $presets[0]->id();
    }

    /**
     * Show what the selection turns on and off, with the entries no package offers kept, and get it in catalog order once the developer confirms it.
     *
     * @param  list<string>  $selection
     * @param  array<string, string>  $left
     * @return list<string>|null
     */
    protected function review(array $selection, array $left): ?array
    {
        $selection = $this->ordered([...$selection, ...$this->unmanaged()]);
        $on = array_values(array_diff($selection, $this->file->classes()));
        $off = array_values(array_diff($this->file->classes(), $selection));

        $skipped = $this->skipped($left);

        if ($on === [] && $off === []) {
            info('The selection is unchanged.');

            if ($skipped !== '') {
                warning($skipped);
            }

            return null;
        }

        note(implode("\n\n", array_filter([$this->listed('Turned on:', $on), $this->listed('Turned off:', $off), $this->kept(), $skipped])));

        return confirm('Write the selection to guardrails.php?') ? $selection : null;
    }

    /**
     * Get the lines that name the checks a preset names that were left out, each with the reason, or nothing when none were.
     *
     * @param  array<string, string>  $left
     */
    protected function skipped(array $left): string
    {
        if ($left === []) {
            return '';
        }

        $lines = array_map(fn (string $class, string $reason) => "  {$class}, because {$reason}", array_keys($left), $left);

        return "Left out:\n".implode("\n", $lines);
    }

    /**
     * Get the line that counts the entries no package offers, or nothing when there are none.
     */
    protected function kept(): string
    {
        $count = count(array_filter($this->file->classes(), fn (string $class) => $this->isUnmanaged($class)));

        if ($count === 0) {
            return '';
        }

        return "Kept as written: {$count} ".($count === 1 ? 'entry' : 'entries').' no package offers.';
    }

    /**
     * Get the heading followed by one indented line per class, or nothing for no classes.
     *
     * @param  list<string>  $classes
     */
    protected function listed(string $heading, array $classes): string
    {
        if ($classes === []) {
            return '';
        }

        return $heading."\n".implode("\n", array_map(fn (string $class) => "  {$class}", $classes));
    }

    /**
     * Put the selection in catalog order, with the checks no pack provides last in the order given.
     *
     * @param  list<string>  $selection
     * @return list<string>
     */
    protected function ordered(array $selection): array
    {
        return array_values(array_unique([...array_intersect($this->offered, $selection), ...$selection]));
    }

    /**
     * Tell the developer the line, through the prompt when asking and through the stream otherwise.
     *
     * @param  resource  $output
     */
    protected function say(string $line, bool $interactive, $output): void
    {
        if ($interactive) {
            info($line);
        } else {
            fwrite($output, $line."\n");
        }
    }

    /**
     * Make sure the pre-commit hook is in place, and get the line that says where it stands.
     */
    protected static function installHook(string $directory): string
    {
        try {
            $installer = HookInstaller::for(Repository::fromDirectory($directory));
        } catch (RuntimeException) {
            return 'Not inside a git repository, so no pre-commit hook was installed.';
        }

        $message = $installer->install()->message($installer);

        return $message === '' ? "The pre-commit hook is in place at {$installer->path}." : $message;
    }
}
