<?php

namespace ClaudioDekker\LaravelGuardrails\Prompts;

use ClaudioDekker\LaravelGuardrails\Group;
use ClaudioDekker\LaravelGuardrails\Preset;
use Laravel\Prompts\Themes\Contracts\Scrolling;
use Laravel\Prompts\Themes\Default\Concerns\DrawsBoxes;
use Laravel\Prompts\Themes\Default\Concerns\DrawsScrollbars;
use Laravel\Prompts\Themes\Default\Renderer;

/**
 * @internal
 */
class TreePromptRenderer extends Renderer implements Scrolling
{
    use DrawsBoxes;
    use DrawsScrollbars;

    /**
     * The line that names the keys.
     */
    protected const KEYS = '↑ ↓ move  ← → open or close  space toggle  p preset  enter finish';

    /**
     * The lines of the frame besides the rows and the explanation: the blank lines above the box, its borders,
     * the blank lines and the name, description and keys under it, and the line the cursor rests on.
     */
    protected const CHROME = 11;

    /**
     * The line that names the keys of the preset choice.
     */
    protected const PRESET_KEYS = 'Enter adds the checks of the preset, esc keeps the tree as it is.';

    /**
     * Draw the tree, the preset choice, or the outcome once the prompt is over.
     */
    public function __invoke(TreePrompt $prompt): string
    {
        $cols = $prompt->terminal()->cols();
        $counts = count($prompt->selected()).' of '.count($prompt->checks()).' on';

        return match (true) {
            $prompt->state === 'submit' => $this->box($this->dim($this->truncate($prompt->label, $cols - 6)), $counts),
            $prompt->state === 'cancel' => $this->box($this->truncate($prompt->label, $cols - 6), $this->tree($prompt), color: 'red')->error($prompt->cancelMessage),
            $prompt->choosingPreset => $this->box($this->cyan('Add a preset'), $this->presets($prompt))->hint(static::PRESET_KEYS),
            default => $this->box($this->cyan($this->truncate($prompt->label, $cols - 6)), $this->tree($prompt), info: $counts)->focus($prompt),
        };
    }

    /**
     * Draw the rows that fit in the box, with a scrollbar when the tree is taller.
     */
    protected function tree(TreePrompt $prompt): string
    {
        $cols = $prompt->terminal()->cols();
        $width = min(max([0, ...array_map(fn (Group $group) => mb_strwidth($group->label()), $prompt->groups)]) + 2, $cols - 29);
        $visible = $prompt->visible();
        $lines = array_map(fn (Row $row, int $index) => $this->row($prompt, $row, $index === $prompt->highlighted, $width), $visible, array_keys($visible));

        return implode(PHP_EOL, $this->scrollbar($lines, $prompt->firstVisible, $prompt->scroll, count($prompt->rows()), min($this->longest($lines, padding: 6), $cols - 6)));
    }

    /**
     * Draw the row: a group with its mark and counts, or a check with its mark and whether it is configured, indented under its group.
     */
    protected function row(TreePrompt $prompt, Row $row, bool $active, int $width): string
    {
        $cursor = $active ? $this->cyan('›') : ' ';

        if ($row->check !== null) {
            $name = $this->truncate($row->check::name(), $prompt->terminal()->cols() - 20);
            $mark = $prompt->isOn($row->check) ? $this->cyan('◼') : $this->dim('◻');
            $configured = $prompt->isConfigured($row->check) ? '  '.$this->dim('configured') : '';

            return "{$cursor}     {$mark} ".($active ? $name : $this->dim($name)).$configured.'  ';
        }

        $on = count($row->group->selected($prompt->value()));
        $total = count($row->group->checks);
        $arrow = $prompt->isOpen($row->group) ? '▾' : '▸';
        $label = $this->pad($this->truncate($row->group->label(), $width - 2), $width);

        $mark = match (true) {
            $on === $total => $this->cyan('◼'),
            $on === 0 => $this->dim('◻'),
            default => $this->cyan('▣'),
        };

        return "{$cursor} {$arrow} {$mark} ".($active ? $label : $this->dim($label))."{$on} of {$total} on  ";
    }

    /**
     * Draw the presets, with the highlighted one marked.
     */
    protected function presets(TreePrompt $prompt): string
    {
        $cols = $prompt->terminal()->cols();

        return implode(PHP_EOL, array_map(function (Preset $preset, int $index) use ($prompt, $cols) {
            $label = $this->truncate($preset->label(), $cols - 12);

            return $index === $prompt->presetHighlighted
                ? "{$this->cyan('› ●')} {$label}  "
                : "  {$this->dim('○')} {$this->dim($label)}  ";
        }, $prompt->presets, array_keys($prompt->presets)));
    }

    /**
     * Write what the highlighted row is, its explanation and the keys, each apart: the name and counts of a group, or the name, coverage and rule of a check.
     */
    protected function focus(TreePrompt $prompt): self
    {
        $width = $prompt->terminal()->cols() - 6;
        $row = $prompt->highlightedRow();
        $this->newLine();

        if ($row->check === null) {
            $on = count($row->group->selected($prompt->value()));
            $total = count($row->group->checks);
            $label = $this->truncate($row->group->label(), $width);
            $rest = $this->truncate(": {$on} of {$total} on. Space turns them all ".($on === $total ? 'off' : 'on').'.', max(1, $width - mb_strwidth($label)));

            $this->line("  {$this->bold($label)}{$rest}");
            $explanation = $row->group->explanation();
        } else {
            $check = $prompt->check($row->check);
            $name = $this->truncate($check::name(), $width);
            $coverage = $this->truncate($check->coverage(), max(1, $width - mb_strwidth($name) - 2));

            $this->line("  {$this->bold($name)}  {$this->dim($coverage)}");
            $this->line('  '.$this->truncate($check->description(), $width));
            $explanation = $check->explanation();
        }

        $this->newLine();

        foreach ($this->paragraph($explanation, $width, $prompt->terminal()->lines() - static::CHROME - $prompt->scroll) as $line) {
            $this->line('  '.$this->dim($line));
        }

        $this->newLine();
        $this->line('  '.$this->dim($this->truncate(static::KEYS, $width)));

        return $this;
    }

    /**
     * Wrap the text to the width, ending the last line that fits in the given number of lines with an ellipsis when the rest does not.
     *
     * @return list<string>
     */
    protected function paragraph(string $text, int $width, int $height): array
    {
        $lines = $this->wrap($text, $width);
        $height = max(1, $height);

        if (count($lines) <= $height) {
            return $lines;
        }

        return [...array_slice($lines, 0, $height - 1), $this->truncate(implode(' ', array_slice($lines, $height - 1)), $width)];
    }

    /**
     * Wrap the text to the width.
     *
     * @return list<string>
     */
    protected function wrap(string $text, int $width): array
    {
        return explode(PHP_EOL, $this->mbWordwrap($text, $width, PHP_EOL, cut_long_words: true));
    }

    /**
     * The number of lines to reserve outside of the scrollable area, which leaves room for the longest explanation the tree can show.
     */
    public function reservedLines(): int
    {
        $prompt = $this->prompt;
        assert($prompt instanceof TreePrompt);

        $width = $prompt->terminal()->cols() - 6;
        $explanations = [
            ...array_map(fn (Group $group) => $group->explanation(), $prompt->groups),
            ...array_map(fn (string $class) => $prompt->check($class)->explanation(), $prompt->checks()),
        ];

        return static::CHROME + max([0, ...array_map(fn (string $explanation) => count($this->wrap($explanation, $width)), $explanations)]);
    }
}
