<?php

namespace ClaudioDekker\LaravelGuardrails;

use DOMDocument;
use InvalidArgumentException;

/**
 * @internal
 */
class PhpUnit
{
    /**
     * The names of PHPUnit's configuration file, in the order PHPUnit reads them.
     */
    protected const CONFIGURATIONS = ['phpunit.xml', 'phpunit.xml.dist'];

    /**
     * Create a handle on the PHPUnit configuration file of the given name, which declares the given test suites.
     *
     * @param  list<TestSuite>  $suites
     */
    public function __construct(public readonly string $name, public readonly array $suites)
    {
        //
    }

    /**
     * Read the PHPUnit configuration of the project, or null when it has none, refusing one that is not valid XML.
     *
     * @throws InvalidArgumentException
     */
    public static function of(Project $project): ?self
    {
        foreach (static::CONFIGURATIONS as $name) {
            $xml = $project->read($name);

            if ($xml !== null) {
                return new self($name, static::suites($project->describe($name), $xml));
            }
        }

        return null;
    }

    /**
     * Read the test suites the XML of the configuration file declares, refusing XML that does not parse with a message that names the file as described.
     *
     * @return list<TestSuite>
     *
     * @throws InvalidArgumentException
     */
    protected static function suites(string $file, string $xml): array
    {
        if (trim($xml) === '') {
            throw new InvalidArgumentException("{$file} is not valid XML: the file is empty.");
        }

        $document = new DOMDocument;
        $internal = libxml_use_internal_errors(true);
        $parsed = $document->loadXML($xml);
        $error = libxml_get_errors()[0] ?? null;

        libxml_clear_errors();
        libxml_use_internal_errors($internal);

        if (! $parsed) {
            $reason = $error === null ? 'it does not parse' : trim($error->message)." (line {$error->line})";

            throw new InvalidArgumentException("{$file} is not valid XML: {$reason}.");
        }

        $suites = [];

        foreach ($document->getElementsByTagName('testsuite') as $element) {
            $suites[] = TestSuite::fromXml($element);
        }

        return $suites;
    }

    /**
     * Get the distinct folders the test suites run, in order.
     *
     * @return list<string>
     */
    public function folders(): array
    {
        return array_values(array_unique(array_merge(...array_map(fn (TestSuite $suite) => $suite->directories, $this->suites))));
    }

    /**
     * Get the distinct folders and files the test suites run, in order.
     *
     * @return list<string>
     */
    public function paths(): array
    {
        return array_values(array_unique(array_merge(...array_map(fn (TestSuite $suite) => [...$suite->directories, ...$suite->files], $this->suites))));
    }

    /**
     * Determine if a test suite runs the test at the given path.
     */
    public function runs(string $path): bool
    {
        foreach ($this->suites as $suite) {
            if ($suite->runs($path)) {
                return true;
            }
        }

        return false;
    }
}
