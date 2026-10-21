<?php

namespace ClaudioDekker\LaravelGuardrails;

use Laravel\Prompts\Terminal;

/**
 * @internal
 */
class ListCommand
{
    /**
     * The usage line printed with an unknown argument or --help.
     */
    protected const USAGE = 'Usage: guardrails list [--details] [--help]';

    /**
     * The width the explanations wrap at when the output is not a terminal.
     */
    protected const WIDTH = 80;

    /**
     * The indent of an explanation, under the name of its check.
     */
    protected const INDENT = '        ';

    /**
     * The mark of a selected check.
     */
    protected const SELECTED = '[x]';

    /**
     * The mark of a check that is not selected.
     */
    protected const UNSELECTED = '[ ]';

    /**
     * The heading of the checks guardrails.php selects that no package offers.
     */
    protected const UNOFFERED = 'guardrails.php';

    /**
     * Create the command for the project with the checks it selects and the packs it discovers, wrapping explanations at the given width, or printing none without one.
     *
     * @param  list<Check>  $selected
     */
    protected function __construct(protected readonly array $selected, protected readonly Catalog $catalog, protected readonly ?int $details = null)
    {
        //
    }

    /**
     * Print every discovered check by package and group, with the selected ones marked and, with --details, explained, and every preset with the checks it holds, and get the exit code.
     *
     * @param  list<string>  $arguments
     * @param  resource  $output
     * @param  resource  $errorOutput
     */
    public static function run(string $directory, array $arguments, $output, $errorOutput): int
    {
        if (in_array('--help', $arguments, true)) {
            fwrite($output, static::USAGE."\n");

            return 0;
        }

        $details = $arguments === ['--details'];

        if ($arguments !== [] && ! $details) {
            fwrite($errorOutput, "Unknown argument {$arguments[0]}. ".static::USAGE."\n");

            return 2;
        }

        $configuration = Configuration::fromProject($directory);
        $catalog = $configuration->discoverable(Catalog::discover($directory));
        $selected = $configuration->checks($catalog);

        $width = stream_isatty($output) ? (new Terminal)->cols() : static::WIDTH;

        fwrite($output, (new self($selected, $catalog, $details ? $width : null))->render());

        return 0;
    }

    /**
     * Render the packs, the selected checks no package offers, then the presets, as one block each.
     */
    protected function render(): string
    {
        $blocks = array_map(fn (Pack $pack) => $this->renderPack($pack), $this->catalog->packs);
        $unoffered = array_values(array_filter($this->selected, fn (Check $check) => $this->catalog->provider($check::class) === null));

        if ($unoffered !== []) {
            $blocks[] = $this->renderUnoffered($unoffered);
        }

        if ($this->catalog->presets() !== []) {
            $blocks[] = $this->renderPresets();
        }

        return implode("\n", $blocks);
    }

    /**
     * Render the pack's name, its checks under their group, and the checks it declares that are not offered.
     */
    protected function renderPack(Pack $pack): string
    {
        $lines = [$pack->name];
        $widths = $this->widths($this->checks($pack->existing()));

        foreach ($pack->groups() as $group) {
            if ($group->name !== '') {
                $lines[] = "  {$group->name}";
            }

            array_push($lines, ...$this->renderRows($this->checks($group->checks), $widths));
        }

        if (end($lines) === '') {
            array_pop($lines);
        }

        foreach ($pack->missing() as $class) {
            $lines[] = "    {$class} does not exist or is not a check, so it is not offered.";
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * Render the selected checks no package offers under the name of the file that selects them.
     *
     * @param  list<Check>  $checks
     */
    protected function renderUnoffered(array $checks): string
    {
        $lines = [static::UNOFFERED, ...$this->renderRows($checks, $this->widths($checks))];

        if (end($lines) === '') {
            array_pop($lines);
        }

        return implode("\n", $lines)."\n";
    }

    /**
     * Render each check's row and, with --details, its explanation and a blank line under it, in columns of the given widths.
     *
     * @param  list<Check>  $checks
     * @param  array{name: int, coverage: int}  $widths
     * @return list<string>
     */
    protected function renderRows(array $checks, array $widths): array
    {
        $lines = [];

        foreach ($checks as $check) {
            $lines[] = '    '.$this->renderCheck($check, $widths);

            if ($this->details !== null) {
                $lines[] = static::INDENT.wordwrap($check->explanation(), $this->details - strlen(static::INDENT), "\n".static::INDENT, cut_long_words: true);
                $lines[] = '';
            }
        }

        return $lines;
    }

    /**
     * Render the check's mark, name, coverage and description in columns of the given widths.
     *
     * @param  array{name: int, coverage: int}  $widths
     */
    protected function renderCheck(Check $check, array $widths): string
    {
        $mark = in_array($check, $this->selected, true) ? static::SELECTED : static::UNSELECTED;

        return rtrim(sprintf('%s %s  %s  %s', $mark, str_pad($check::name(), $widths['name']), str_pad($check->coverage(), $widths['coverage']), $check->description()));
    }

    /**
     * Get the widths of the name and coverage columns that fit the given checks.
     *
     * @param  list<Check>  $checks
     * @return array{name: int, coverage: int}
     */
    protected function widths(array $checks): array
    {
        return [
            'name' => max([0, ...array_map(fn (Check $check) => strlen($check::name()), $checks)]),
            'coverage' => max([0, ...array_map(fn (Check $check) => strlen($check->coverage()), $checks)]),
        ];
    }

    /**
     * Get the checks of the given classes: each selected check of a class, or one with its defaults when none is.
     *
     * @param  list<class-string<Check>>  $classes
     * @return list<Check>
     */
    protected function checks(array $classes): array
    {
        return array_merge(...array_map(fn (string $class) => $this->selectedOf($class) ?: [new $class], $classes));
    }

    /**
     * Get the selected checks of the given class, in the order guardrails.php lists them.
     *
     * @return list<Check>
     */
    protected function selectedOf(string $class): array
    {
        return array_values(array_filter($this->selected, fn (Check $check) => $check::class === $class));
    }

    /**
     * Render every preset under a heading, each with the names of the checks it holds.
     */
    protected function renderPresets(): string
    {
        $lines = ['Presets'];

        foreach ($this->catalog->presets() as $preset) {
            $lines[] = "  {$preset->name} ({$preset->pack->name})";

            if ($preset->description !== '') {
                $lines[] = "    {$preset->description}";
            }

            $lines[] = '    '.implode(', ', array_map(fn (string $class) => basename(str_replace('\\', '/', $class)), $preset->checks));
        }

        return implode("\n", $lines)."\n";
    }
}
