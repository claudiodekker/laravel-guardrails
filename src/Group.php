<?php

namespace ClaudioDekker\LaravelGuardrails;

/**
 * @internal
 */
class Group
{
    /**
     * The text between the segments of a group's name.
     */
    public const SEPARATOR = ' › ';

    /**
     * The explanations of the built-in groups, by name.
     */
    protected const EXPLANATIONS = [
        'Text' => 'These checks read every file in the repository, whatever its language. They keep each file short enough for an agent to read whole, and free of em dashes.',
        'Php › Tests' => 'These checks read the test suite. They make sure every test runs, keep every test in a Unit or Feature folder, keep HTTP requests out of unit tests, and keep test files free of named functions and of docblocks that repeat a test\'s name.',
        'Php › Structure' => 'These checks govern where PHP code lives and how its classes are declared. They keep one class to a file, only traits in Concerns folders, test code and reflection out of the application, and every class open to extension.',
        'Php › Enums' => 'These checks read enum declarations. They keep the cases of an enum in alphabetical order, and documented all alike.',
        'Php › Safety' => 'These checks flag PHP functions that open a security hole, or leak debug output, in production. Each failure names the safe function to use instead.',
        'Php › Shape' => 'These checks govern the layout of statements within a file. They keep blank lines and line breaks where a reader expects them, and split dense one-liners into named steps.',
        'Php › Comments' => 'These checks read comments and docblocks. They require a docblock on every method, function and property, keep docblocks and inline comments short, and make each PHPStan ignore name the error it hides.',
        'Php › Config' => 'These checks keep configuration in the config files and easy to read. They keep env() inside config files, every default in the config file rather than at the call site, durations written as products, and keys in alphabetical order.',
        'Php › Database' => 'These checks read the migrations. They catch column types that lose data, such as money stored as a float.',
    ];

    /**
     * Create a group of the given checks of the pack, named by their namespace below the one the pack's checks share.
     *
     * @param  list<class-string<Check>>  $checks
     */
    public function __construct(public readonly Pack $pack, public readonly string $name, public readonly array $checks)
    {
        //
    }

    /**
     * Get the label of the group in a menu, which names the package unless the group is built in.
     */
    public function label(): string
    {
        if ($this->pack->isBuiltIn()) {
            return $this->name;
        }

        return $this->name === '' ? $this->pack->name : $this->pack->name.static::SEPARATOR.$this->name;
    }

    /**
     * Get what the group's checks have in common, which names the package unless the group is built in.
     */
    public function explanation(): string
    {
        if ($this->pack->isBuiltIn()) {
            return static::EXPLANATIONS[$this->name];
        }

        return "The checks {$this->pack->name} provides.";
    }

    /**
     * Get the group's checks that are in the given selection.
     *
     * @param  list<string>  $selection
     * @return list<class-string<Check>>
     */
    public function selected(array $selection): array
    {
        return array_values(array_intersect($this->checks, $selection));
    }
}
