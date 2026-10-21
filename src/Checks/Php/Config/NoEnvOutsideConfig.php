<?php

namespace ClaudioDekker\LaravelGuardrails\Checks\Php\Config;

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Violation;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;

/**
 * @api
 */
class NoEnvOutsideConfig extends Check
{
    /**
     * Get the one-line description of the rule, shown when the checks are chosen.
     */
    public function description(): string
    {
        return 'No env() or Env::get() outside a config folder.';
    }

    /**
     * Get the explanation of the rule, shown under the checks when they are chosen.
     */
    public function explanation(): string
    {
        return 'Once php artisan config:cache has run, Laravel no longer loads the .env file, so env() outside the config files returns null for every value set there. This flags env() and Env::get() in any PHP file, tests included, except in a config folder at any depth. Read the value with env() in a config file, and with config() everywhere else.';
    }

    /**
     * Get the kinds of code the check reads.
     *
     * @return list<Kind>
     */
    public function kinds(): array
    {
        return Kind::except(Kind::CONFIG);
    }

    /**
     * Get the one-line description of the files the check reads.
     */
    public function scope(): string
    {
        return 'PHP outside config folders';
    }

    /**
     * Get the failure message, naming the fix.
     */
    public function message(): string
    {
        return 'Read the value through config(), because env() returns null once the config is cached.';
    }

    /**
     * Determine if the check reads the file, which is any PHP file outside a config folder.
     */
    protected function reads(File $file): bool
    {
        return parent::reads($file) && ! $file->isIn('config');
    }

    /**
     * Get a violation at the name of each call to env() or Env::get().
     *
     * @return list<Violation>
     */
    protected function violations(File $file): array
    {
        $calls = [...$file->findInstanceOf(FuncCall::class), ...$file->findInstanceOf(StaticCall::class)];
        $reads = array_filter($calls, fn (FuncCall|StaticCall $call) => $this->readsEnvironment($call));

        return array_map(fn (FuncCall|StaticCall $call) => Violation::at($call->name), array_values($reads));
    }

    /**
     * Determine if the call is to env() or Env::get().
     */
    protected function readsEnvironment(FuncCall|StaticCall $call): bool
    {
        if ($call instanceof FuncCall) {
            return $call->name instanceof Node\Name && $call->name->toLowerString() === 'env';
        }

        return $call->class instanceof Node\Name
            && $call->class->getLast() === 'Env'
            && $call->name instanceof Identifier
            && $call->name->toLowerString() === 'get';
    }
}
