<?php

namespace ClaudioDekker\LaravelGuardrails\Checks\Php\Package;

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\Checks\Php\Package\Concerns\CoversFolders;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Violation;
use PhpParser\Node;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\ClassLike;
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
        return 'A mixed type promises nothing, so its users must guess what a value holds and the analyser cannot check it. This flags mixed as the declared type of a parameter, a promoted property, a return or a property, in functions, methods and closures alike. Docblock types are left alone. A mixed is allowed for a template type, which PHP cannot declare, when its docblock names a @template of the function or its class. It takes the folders it covers, src by default, as in new NoMixedTypes([\'packages/*/src\']), so an app can point it at a folder it treats as a package, such as app/Billing.';
    }

    /**
     * Get the failure message, naming the fix.
     */
    public function message(): string
    {
        return 'Replace mixed with the types the value can hold, as a union when it is more than one.';
    }

    /**
     * Get a violation at each parameter, return or property type that is mixed, unless its docblock names a template type.
     *
     * @return list<Violation>
     */
    protected function violations(File $file): array
    {
        $scopes = array_merge($file->findInstanceOf(ClassLike::class), $file->findInstanceOf(FunctionLike::class));
        $declared = [];

        foreach ($file->findInstanceOf(FunctionLike::class) as $function) {
            $templates = $this->templatesAround($function, $scopes);
            $docblock = $function->getDocComment()?->getText() ?? '';

            $declared[] = [$function->getReturnType(), $this->tagType($docblock, 'return'), $templates];

            foreach ($function->getParams() as $param) {
                $declared[] = [$param->type, $this->paramType($docblock, $param), $templates];
            }
        }

        foreach ($file->findInstanceOf(Property::class) as $property) {
            $docblock = $property->getDocComment()?->getText() ?? '';

            $declared[] = [$property->type, $this->tagType($docblock, 'var'), $this->templatesAround($property, $scopes)];
        }

        $violations = [];

        foreach ($declared as [$type, $docType, $templates]) {
            $isMixed = $type instanceof Identifier && $type->toLowerString() === 'mixed';

            if ($isMixed && ! $this->namesTemplate($docType, $templates)) {
                $violations[] = Violation::at($type);
            }
        }

        return $violations;
    }

    /**
     * Get the template names declared on the node and on every class-like or function that holds it.
     *
     * @param  list<Node>  $scopes
     * @return list<string>
     */
    protected function templatesAround(Node $node, array $scopes): array
    {
        $templates = [];

        foreach ($scopes as $scope) {
            $holdsNode = $scope->getStartFilePos() <= $node->getStartFilePos()
                && $node->getEndFilePos() <= $scope->getEndFilePos();

            if (! $holdsNode) {
                continue;
            }

            $docblock = $scope->getDocComment()?->getText() ?? '';
            preg_match_all('/@(?:phpstan-|psalm-)?template(?:-covariant|-contravariant)?\s+(\w+)/', $docblock, $matches);
            $templates = array_merge($templates, $matches[1]);
        }

        return $templates;
    }

    /**
     * Get the type the docblock gives the parameter.
     */
    protected function paramType(string $docblock, Param $param): ?string
    {
        $name = $param->var instanceof Variable ? $param->var->name : null;

        if (! is_string($name)) {
            return null;
        }

        return $this->tagType($docblock, 'param', $name);
    }

    /**
     * Get the type the docblock's tag gives, narrowed to the named parameter for a @param tag.
     */
    protected function tagType(string $docblock, string $tag, ?string $parameter = null): ?string
    {
        $variable = $parameter === null ? '' : '\s+\.{0,3}\$'.preg_quote($parameter, '/').'\b';

        return preg_match('/@'.$tag.'\s+(\S+)'.$variable.'/', $docblock, $matches) === 1 ? $matches[1] : null;
    }

    /**
     * Determine if the docblock type is a template name alone, or a union of template names and null.
     *
     * @param  list<string>  $templates
     */
    protected function namesTemplate(?string $type, array $templates): bool
    {
        if ($type === null) {
            return false;
        }

        $names = explode('|', ltrim($type, '?'));
        $allowed = array_merge($templates, ['null']);

        return array_diff($names, $allowed) === [] && array_intersect($names, $templates) !== [];
    }
}
