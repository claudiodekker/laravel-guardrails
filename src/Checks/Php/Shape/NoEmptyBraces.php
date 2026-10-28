<?php

namespace ClaudioDekker\LaravelGuardrails\Checks\Php\Shape;

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Violation;
use PhpParser\Node;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Token;

/**
 * @api
 */
class NoEmptyBraces extends Check
{
    /**
     * Get the one-line description of the rule, shown when the checks are chosen.
     */
    public function description(): string
    {
        return 'An empty body holds a lone //, not empty braces.';
    }

    /**
     * Get the explanation of the rule, shown under the checks when they are chosen.
     */
    public function explanation(): string
    {
        return 'An empty body written as {} reads like a body someone forgot to write, while a // between the braces says it is empty on purpose, the way Laravel writes an empty constructor or class. This flags braces that hold nothing but whitespace, whether they share a line or not, around the body of a function, method, class or control structure. A closure or anonymous class passed as a value keeps its {}, and a body that holds a comment passes.';
    }

    /**
     * Get the failure message, naming the fix.
     */
    public function message(): string
    {
        return 'Write the empty body over three lines: the {, a line holding only //, and the }.';
    }

    /**
     * Get a violation at each pair of braces that holds nothing but whitespace, outside a closure or an anonymous class.
     *
     * @return list<Violation>
     */
    protected function violations(File $file): array
    {
        $tokens = $file->tokens();
        $values = array_map(fn (Node $node) => $node->getEndTokenPos(), $file->find(fn (Node $node) => $this->isClosureOrAnonymousClass($node)));
        $violations = [];

        foreach ($tokens as $index => $token) {
            $closing = $this->closingBrace($tokens, $index);

            if ($closing !== null && ! in_array($closing, $values, true)) {
                $violations[] = Violation::lines($token->line, $tokens[$closing]->line);
            }
        }

        return $violations;
    }

    /**
     * Get the position of the } that directly closes the { at the given position, with only whitespace between them.
     *
     * @param  array<Token>  $tokens
     */
    protected function closingBrace(array $tokens, int $index): ?int
    {
        if ($tokens[$index]->text !== '{') {
            return null;
        }

        $next = $index + 1;

        if (($tokens[$next] ?? null)?->id === T_WHITESPACE) {
            $next++;
        }

        return ($tokens[$next] ?? null)?->text === '}' ? $next : null;
    }

    /**
     * Determine if the node is a closure or an anonymous class, which may keep its braces empty when passed as a value.
     */
    protected function isClosureOrAnonymousClass(Node $node): bool
    {
        return $node instanceof Closure || ($node instanceof Class_ && $node->isAnonymous());
    }
}
