<?php

namespace ClaudioDekker\LaravelGuardrails\Checks\Php\Safety;

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\Concerns\SkipsFakeData;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Violation;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;

/**
 * @api
 */
class NoWeakHashes extends Check
{
    use SkipsFakeData;

    /**
     * The functions the check refuses, lowercase.
     */
    protected const FUNCTIONS = ['md5', 'sha1'];

    /**
     * Get the one-line description of the rule, shown when the checks are chosen.
     */
    public function description(): string
    {
        return 'No md5() or sha1() outside tests.';
    }

    /**
     * Get the explanation of the rule, shown under the checks when they are chosen.
     */
    public function explanation(): string
    {
        return 'md5() and sha1() are broken, so an attacker can build two inputs with the same hash, and both are fast enough to brute force a password. This flags every call outside tests and the database factories and seeders, which make fake data, even for a cache key or a checksum. Use hash(\'sha256\', $value) instead, hash_hmac() to sign a value, or Hash::make() for a password.';
    }

    /**
     * Get the kinds of code the check reads.
     *
     * @return list<Kind>
     */
    public function kinds(): array
    {
        return Kind::except(Kind::TESTS);
    }

    /**
     * Get the failure message, naming the fix.
     */
    public function message(): string
    {
        return "Use hash('sha256', ...) or hash_hmac() instead, because md5() and sha1() are broken as hashes.";
    }

    /**
     * Get a violation at the name of each call to md5() or sha1().
     *
     * @return list<Violation>
     */
    protected function violations(File $file): array
    {
        $calls = array_filter($file->findInstanceOf(FuncCall::class), fn (FuncCall $call) => $this->isRefused($call));

        return array_map(fn (FuncCall $call) => Violation::at($call->name), array_values($calls));
    }

    /**
     * Determine if the call is to md5() or sha1().
     */
    protected function isRefused(FuncCall $call): bool
    {
        return $call->name instanceof Node\Name && in_array($call->name->toLowerString(), static::FUNCTIONS, true);
    }
}
