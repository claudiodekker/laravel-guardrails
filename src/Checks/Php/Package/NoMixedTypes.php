<?php

namespace ClaudioDekker\LaravelGuardrails\Checks\Php\Package;

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\Checks\Php\Package\Concerns\CoversFolders;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Violation;
use PhpParser\Node;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\Property;

/**
 * @api
 */
class NoMixedTypes extends Check
{
    use CoversFolders;

    /**
     * Get the one-line description of the rule, shown when the checks are chosen.
     */
    public function description(): string
    {
        return 'No mixed as a parameter, return or property type.';
    }

    /**
     * Get the explanation of the rule, shown under the checks when they are chosen.
     */
    public function explanation(): string
    {
        return 'A mixed type promises nothing, so its users must guess what a value holds and the analyser cannot check it. This flags mixed as the declared type of a parameter, a promoted property, a return or a property, in functions, methods and closures alike. Docblock types are left alone. It takes the folders it covers, src by default, as in new NoMixedTypes([\'packages/*/src\']), so an app can point it at a folder it treats as a package, such as app/Billing.';
    }

    /**
     * Get the failure message, naming the fix.
     */
    public function message(): string
    {
        return 'Replace mixed with the types the value can hold, as a union when it is more than one.';
    }

    /**
     * Get a violation at each parameter, return or property type that is mixed.
     *
     * @return list<Violation>
     */
    protected function violations(File $file): array
    {
        $types = [];

        foreach ($file->findInstanceOf(FunctionLike::class) as $function) {
            $types[] = $function->getReturnType();

            foreach ($function->getParams() as $param) {
                $types[] = $param->type;
            }
        }

        foreach ($file->findInstanceOf(Property::class) as $property) {
            $types[] = $property->type;
        }

        $mixed = array_filter($types, fn (?Node $type) => $type instanceof Identifier && $type->toLowerString() === 'mixed');

        return array_map(Violation::at(...), array_values($mixed));
    }
}
