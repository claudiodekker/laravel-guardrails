<?php

namespace ClaudioDekker\LaravelGuardrails\Checks\Php\Tests;

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Violation;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;

/**
 * @api
 */
class NoHttpInUnitTests extends Check
{
    /**
     * The request methods of Laravel's test case, which Pest's Laravel functions share.
     */
    protected const REQUESTS = [
        'call',
        'delete',
        'deleteJson',
        'get',
        'getJson',
        'json',
        'options',
        'optionsJson',
        'patch',
        'patchJson',
        'post',
        'postJson',
        'put',
        'putJson',
    ];

    /**
     * The name of the test folder the rule holds in, at any depth.
     */
    protected string $folder = 'Unit';

    /**
     * Get the one-line description of the rule, shown when the checks are chosen.
     */
    public function description(): string
    {
        return 'No HTTP request in a unit test, through the test case or Pest\'s Laravel functions.';
    }

    /**
     * Get the explanation of the rule, shown under the checks when they are chosen.
     */
    public function explanation(): string
    {
        return 'A request runs the routes, middleware and controllers, so the test is a feature test and needs the booted app of a Feature folder. This flags $this->get(), post(), json(), call() and the other request methods of Laravel\'s test case, and the same functions from Pest\\Laravel, in a Unit folder. Calls on other objects, such as $this->app->get(), are left alone. Move the test to tests/Feature.';
    }

    /**
     * Get the kinds of code the check reads.
     *
     * @return list<Kind>
     */
    public function kinds(): array
    {
        return [Kind::TESTS];
    }

    /**
     * Get the one-line description of the files the check reads.
     */
    public function scope(): string
    {
        return "tests in a {$this->folder} folder";
    }

    /**
     * Get the failure message, naming the fix.
     */
    public function message(): string
    {
        return 'Move the test to tests/Feature, because a unit test runs without the HTTP kernel.';
    }

    /**
     * Determine if the check reads the file, which is any test in the folder the rule holds in.
     */
    protected function reads(File $file): bool
    {
        return parent::reads($file) && $file->isIn($this->folder);
    }

    /**
     * Get a violation at the name of each request through the test case or Pest's Laravel functions.
     *
     * @return list<Violation>
     */
    protected function violations(File $file): array
    {
        $calls = [...$file->findInstanceOf(FuncCall::class), ...$file->findInstanceOf(MethodCall::class)];
        $requests = array_filter($calls, fn (FuncCall|MethodCall $call) => $this->isRequest($call));

        return array_map(fn (FuncCall|MethodCall $call) => Violation::at($call->name), array_values($requests));
    }

    /**
     * Determine if the call sends a request, as $this->post() or Pest\Laravel\post() does.
     */
    protected function isRequest(FuncCall|MethodCall $call): bool
    {
        if ($call instanceof FuncCall) {
            return $call->name instanceof Node\Name && $this->isRequestFunction($call->name);
        }

        return $call->var instanceof Variable
            && $call->var->name === 'this'
            && $call->name instanceof Identifier
            && $this->isRequestMethod($call->name->toString());
    }

    /**
     * Determine if the function is one of Pest's Laravel request functions, by its resolved name.
     */
    protected function isRequestFunction(Node\Name $name): bool
    {
        $functions = array_map(fn (string $request) => 'pest\\laravel\\'.strtolower($request), static::REQUESTS);

        return in_array($name->toLowerString(), $functions, true);
    }

    /**
     * Determine if the name is one of the request methods, ignoring case.
     */
    protected function isRequestMethod(string $name): bool
    {
        return in_array(strtolower($name), array_map(strtolower(...), static::REQUESTS), true);
    }
}
