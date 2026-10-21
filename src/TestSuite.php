<?php

namespace ClaudioDekker\LaravelGuardrails;

use DOMElement;

/**
 * @internal
 */
class TestSuite
{
    /**
     * Create a test suite that runs the tests under the given folders and the given files, except the excluded folders and files.
     *
     * @param  list<string>  $directories
     * @param  list<string>  $files
     * @param  list<string>  $excludes
     */
    public function __construct(public readonly array $directories, public readonly array $files = [], public readonly array $excludes = [])
    {
        //
    }

    /**
     * Read a test suite from its element in a phpunit.xml.
     */
    public static function fromXml(DOMElement $element): self
    {
        return new self(self::paths($element, 'directory'), self::paths($element, 'file'), self::paths($element, 'exclude'));
    }

    /**
     * Determine if the suite runs the test at the given path.
     */
    public function runs(string $path): bool
    {
        foreach ($this->excludes as $exclude) {
            if (Layout::holds($exclude, $path)) {
                return false;
            }
        }

        foreach ([...$this->directories, ...$this->files] as $root) {
            if (Layout::holds($root, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get the paths of the element's children with the given tag, normalized from the project root.
     *
     * @return list<string>
     */
    protected static function paths(DOMElement $element, string $tag): array
    {
        $paths = [];

        foreach ($element->getElementsByTagName($tag) as $child) {
            $paths[] = Layout::normalize(trim($child->textContent));
        }

        return $paths;
    }
}
