<?php

namespace ClaudioDekker\LaravelGuardrails\Prompts;

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\Group;
use ClaudioDekker\LaravelGuardrails\Preset;
use Laravel\Prompts\Concerns\Scrolling;
use Laravel\Prompts\Key;
use Laravel\Prompts\Prompt;

/**
 * @internal
 */
class TreePrompt extends Prompt
{
    use Scrolling;

    /**
     * The name of the theme that draws the tree.
     */
    protected const THEME = 'guardrails';

    /**
     * Whether the prompt shows the presets instead of the tree.
     */
    public bool $choosingPreset = false;

    /**
     * The index of the highlighted preset.
     */
    public int $presetHighlighted = 0;

    /**
     * Whether a selection is required. Prompt::validate() reads it on submit, so it must be set.
     */
    public bool|string $required = false;

    /**
     * The validator, which Prompt::validate() reads on submit even though the tree has none.
     */
    public mixed $validate = null;

    /**
     * The groups that are open, showing their checks.
     *
     * @var list<Group>
     */
    protected array $open = [];

    /**
     * The checks built so far, by class.
     *
     * @var array<class-string<Check>, Check>
     */
    protected array $checks = [];

    /**
     * The number of rows the tree shows at most, when the terminal has room for them.
     */
    protected readonly int $height;

    /**
     * Create a prompt that offers the groups as a tree, with the given checks on, the given ones configured through their constructors, and the presets to add to it.
     *
     * @param  list<Group>  $groups
     * @param  list<Preset>  $presets
     * @param  list<string>  $selection
     * @param  list<Check>  $configured
     */
    public function __construct(
        public readonly string $label,
        public readonly array $groups,
        public readonly array $presets,
        protected array $selection,
        protected array $configured = [],
        public int $scroll = 10,
    ) {
        static::addTheme(static::THEME, [self::class => TreePromptRenderer::class]);
        static::theme(static::THEME);

        $this->height = $scroll;
        $this->initializeScrolling(0);

        $this->on('key', fn (string $key) => $this->choosingPreset ? $this->handlePresetKey($key) : $this->handleTreeKey($key));
    }

    /**
     * Get the classes of the checks that are on.
     *
     * @return list<string>
     */
    public function value(): array
    {
        return $this->selection;
    }

    /**
     * Get every check the tree offers, in group order.
     *
     * @return list<class-string<Check>>
     */
    public function checks(): array
    {
        return array_merge(...array_map(fn (Group $group) => $group->checks, $this->groups));
    }

    /**
     * Get the offered checks that are on, in group order.
     *
     * @return list<class-string<Check>>
     */
    public function selected(): array
    {
        return array_values(array_intersect($this->checks(), $this->selection));
    }

    /**
     * Get the rows of the tree: each group, followed by its checks when it is open.
     *
     * @return list<Row>
     */
    public function rows(): array
    {
        $rows = [];

        foreach ($this->groups as $group) {
            $rows[] = new Row($group);

            foreach ($this->isOpen($group) ? $group->checks : [] as $class) {
                $rows[] = new Row($group, $class);
            }
        }

        return $rows;
    }

    /**
     * Get the rows that fit in the box, keyed by their index in the tree.
     *
     * @return array<int, Row>
     */
    public function visible(): array
    {
        return array_slice($this->rows(), $this->firstVisible, $this->scroll, preserve_keys: true);
    }

    /**
     * Get the highlighted row.
     */
    public function highlightedRow(): Row
    {
        return $this->rows()[$this->highlighted ?? 0];
    }

    /**
     * Determine if the group shows its checks.
     */
    public function isOpen(Group $group): bool
    {
        return in_array($group, $this->open, true);
    }

    /**
     * Determine if the check is on.
     */
    public function isOn(string $class): bool
    {
        return in_array($class, $this->selection, true);
    }

    /**
     * Determine if the check is configured through its constructor.
     */
    public function isConfigured(string $class): bool
    {
        return $this->instance($class) !== null;
    }

    /**
     * Get the check of the given class, as configured or with its defaults.
     *
     * @param  class-string<Check>  $class
     */
    public function check(string $class): Check
    {
        return $this->checks[$class] ??= $this->instance($class) ?? new $class;
    }

    /**
     * Get the instance the check of the given class is configured as, if it is one.
     */
    protected function instance(string $class): ?Check
    {
        foreach ($this->configured as $check) {
            if ($check::class === $class) {
                return $check;
            }
        }

        return null;
    }

    /**
     * Fit the rows to the terminal as it is now, keeping the highlighted one in view, and draw the prompt.
     */
    protected function renderTheme(): string
    {
        $this->scroll = $this->height;
        $this->reduceScrollingToFitTerminal();
        $this->firstVisible = max(0, min($this->firstVisible, count($this->rows()) - $this->scroll));
        $this->highlight($this->highlighted);

        return parent::renderTheme();
    }

    /**
     * Move, open, close, toggle, show the presets or finish, as the key says.
     */
    protected function handleTreeKey(string $key): void
    {
        match ($key) {
            Key::UP, Key::UP_ARROW, Key::CTRL_P, 'k' => $this->highlightPrevious(count($this->rows())),
            Key::DOWN, Key::DOWN_ARROW, Key::CTRL_N, 'j' => $this->highlightNext(count($this->rows())),
            Key::RIGHT, Key::RIGHT_ARROW, 'l' => $this->expand(),
            Key::LEFT, Key::LEFT_ARROW, 'h' => $this->collapse(),
            Key::oneOf(Key::HOME, $key) => $this->highlight(0),
            Key::oneOf(Key::END, $key) => $this->highlight(count($this->rows()) - 1),
            Key::SPACE => $this->toggle(),
            'p' => $this->choosePreset(),
            Key::ENTER => $this->submit(),
            default => null,
        };
    }

    /**
     * Move between the presets, apply the highlighted one or go back to the tree, as the key says.
     */
    protected function handlePresetKey(string $key): void
    {
        $total = count($this->presets);

        match ($key) {
            Key::UP, Key::UP_ARROW, Key::CTRL_P, 'k' => $this->presetHighlighted = ($this->presetHighlighted + $total - 1) % $total,
            Key::DOWN, Key::DOWN_ARROW, Key::CTRL_N, 'j' => $this->presetHighlighted = ($this->presetHighlighted + 1) % $total,
            Key::ENTER => $this->applyPreset(),
            Key::ESCAPE, Key::LEFT, Key::LEFT_ARROW, 'h', 'p' => $this->choosingPreset = false,
            default => null,
        };
    }

    /**
     * Show the presets, from the first, when there are any.
     */
    protected function choosePreset(): void
    {
        $this->choosingPreset = $this->presets !== [];
        $this->presetHighlighted = 0;
    }

    /**
     * Show the checks of the highlighted group.
     */
    protected function expand(): void
    {
        $row = $this->highlightedRow();

        if ($row->check === null && ! $this->isOpen($row->group)) {
            $this->open[] = $row->group;
        }
    }

    /**
     * Hide the checks of the highlighted group, or move from a check to its group.
     */
    protected function collapse(): void
    {
        $row = $this->highlightedRow();

        if ($row->check !== null) {
            $this->highlight($this->indexOf($row->group));

            return;
        }

        $this->open = array_values(array_filter($this->open, fn (Group $open) => $open !== $row->group));
    }

    /**
     * Get the index of the group's own row.
     */
    protected function indexOf(Group $group): int
    {
        foreach ($this->rows() as $index => $row) {
            if ($row->check === null && $row->group === $group) {
                return $index;
            }
        }

        return 0;
    }

    /**
     * Turn the highlighted check on or off, or every check of the highlighted group.
     */
    protected function toggle(): void
    {
        $row = $this->highlightedRow();

        if ($row->check !== null) {
            $this->selection = $this->isOn($row->check)
                ? array_values(array_diff($this->selection, [$row->check]))
                : [...$this->selection, $row->check];

            return;
        }

        $without = array_values(array_diff($this->selection, $row->group->checks));
        $allOn = count($row->group->selected($this->selection)) === count($row->group->checks);

        $this->selection = $allOn ? $without : [...$without, ...$row->group->checks];
    }

    /**
     * Turn on the checks of the highlighted preset as well as the ones already on, and show the tree again.
     */
    protected function applyPreset(): void
    {
        $this->selection = array_values(array_unique([...$this->selection, ...$this->presets[$this->presetHighlighted]->checks]));
        $this->choosingPreset = false;
    }
}
