<?php

namespace ClaudioDekker\LaravelGuardrails\Checks\Text;

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Violation;
use DOMDocument;
use DOMElement;

/**
 * @api
 */
class NoPhpUnitExclusions extends Check
{
    /**
     * Get the one-line description of the rule, shown when the checks are chosen.
     */
    public function description(): string
    {
        return 'No <exclude> in the PHPUnit configuration.';
    }

    /**
     * Get the explanation of the rule, shown under the checks when they are chosen.
     */
    public function explanation(): string
    {
        return 'An <exclude> in phpunit.xml takes tests or code out of the run, so a failing test can be made to pass by hiding it. This flags each directory, file or group listed in an <exclude> of the test suites, the groups to run and the source code, and a test suite\'s own <exclude> path, in phpunit.xml, phpunit.xml.dist and phpunit.dist.xml. A configuration that does not parse passes, and so does one with only <include> elements. Fix the test, or delete a test of behavior that no longer exists.';
    }

    /**
     * Get the failure message, naming the fix.
     */
    public function message(): string
    {
        return 'Remove the exclusion and fix the tests or the code it hides, because an excluded test or file is no longer checked.';
    }

    /**
     * Get the kinds of code the check reads.
     *
     * @return list<Kind>
     */
    public function kinds(): array
    {
        return [Kind::TEXT];
    }

    /**
     * Get the patterns of the names of the files the check reads, which are PHPUnit's configuration files.
     *
     * @return list<string>
     */
    protected function files(): array
    {
        return ['phpunit.xml', 'phpunit.xml.dist', 'phpunit.dist.xml'];
    }

    /**
     * Get a violation at each element an <exclude> lists, or at the <exclude> of a test suite, which holds its path as text.
     *
     * @return list<Violation>
     */
    protected function violations(File $file): array
    {
        $document = $this->parse($file->contents);

        if ($document === null) {
            return [];
        }

        $violations = [];

        foreach ($document->getElementsByTagName('exclude') as $exclude) {
            foreach ($this->excluded($exclude) as $element) {
                $violations[] = Violation::line($element->getLineNo());
            }
        }

        return $violations;
    }

    /**
     * Get the elements the <exclude> excludes with, which are its children, or the <exclude> itself in a test suite, where the path is its text.
     *
     * @return list<DOMElement>
     */
    protected function excluded(DOMElement $exclude): array
    {
        if ($exclude->parentNode?->nodeName === 'testsuite') {
            return [$exclude];
        }

        $children = [];

        foreach ($exclude->childNodes as $child) {
            if ($child instanceof DOMElement) {
                $children[] = $child;
            }
        }

        return $children;
    }

    /**
     * Parse the XML, or get null when it does not parse.
     */
    protected function parse(string $xml): ?DOMDocument
    {
        if (trim($xml) === '') {
            return null;
        }

        $document = new DOMDocument;
        $internal = libxml_use_internal_errors(true);
        $parsed = $document->loadXML($xml, LIBXML_NONET);

        libxml_clear_errors();
        libxml_use_internal_errors($internal);

        return $parsed ? $document : null;
    }
}
