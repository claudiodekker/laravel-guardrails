<?php

namespace ClaudioDekker\LaravelGuardrails\Checks\Php\Comments;

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Violation;

/**
 * @api
 */
class SlopedComments extends Check
{
    /**
     * The pattern of the first line of a list of supported values, which ends its paragraph.
     */
    protected const LIST = '/^[A-Za-z][A-Za-z ]*:\s+"/';

    /**
     * The pattern of a line of commented-out code: an option, a closing bracket or a statement.
     */
    protected const CODE = '/ => |^[\])}]+[,;]?$|;$/';

    /**
     * Create the check, which also reads the paragraphs of the block comments that head a config file when told to.
     */
    public function __construct(public readonly bool $banners = false)
    {
        //
    }

    /**
     * Get the one-line description of the rule, shown when the checks are chosen.
     */
    public function description(): string
    {
        if ($this->banners) {
            return 'A // comment or a paragraph of a banner of several lines is a slope of three lines, each 2 to 4 characters shorter than the one above.';
        }

        return 'A // comment of several lines is a slope of three lines, each 2 to 4 characters shorter than the one above.';
    }

    /**
     * Get the explanation of the rule, shown under the checks when they are chosen.
     */
    public function explanation(): string
    {
        if ($this->banners) {
            return 'This flags a // comment that runs over several consecutive lines, and a paragraph of several lines in a /* */ banner whose lines start with |, unless it is a slope of exactly three lines, each 2 to 4 characters shorter than the one above. A paragraph ends at a blank | line or a |---- rule, so a title between two rules is one line and passes. The shape caps a comment at three lines. A single // line, a // comment after code, # comments and docblocks are left alone. Config folders are read too. A list that ends a paragraph and starts with a label and a quoted value, such as Supported: "a", "b", is left alone, and so is commented-out code, which is a comment with a => or a closing bracket on a line or a line that ends in a semicolon.';
        }

        return 'This flags a // comment that runs over several consecutive lines, unless it is a slope of exactly three lines, each 2 to 4 characters shorter than the one above. The shape caps an inline comment at three lines. A single // line, a // comment after code, # and /* */ comments and docblocks are left alone, and so are config folders, which comment out the options they offer, and commented-out code, which is a comment with a => or a closing bracket on a line or a line that ends in a semicolon. Pass banners: true to hold the paragraphs of a /* */ banner to the slope too.';
    }

    /**
     * Get the kinds of code the check reads.
     *
     * @return list<Kind>
     */
    public function kinds(): array
    {
        return $this->banners ? Kind::cases() : Kind::except(Kind::CONFIG);
    }

    /**
     * Get the one-line description of the files the check reads.
     */
    public function scope(): string
    {
        return $this->banners ? 'PHP' : 'PHP outside config folders';
    }

    /**
     * Get the failure message, naming the fix.
     */
    public function message(): string
    {
        return 'Reword the comment into a slope: three lines, each 2 to 4 characters shorter than the one above.';
    }

    /**
     * Determine if the check reads the file, which is any PHP file, outside a config folder unless it reads banners.
     */
    protected function reads(File $file): bool
    {
        return parent::reads($file) && ($this->banners || ! $file->isIn('config'));
    }

    /**
     * Get a violation at the first line of every run of // comments, and with banners every paragraph of a block comment, that spans several lines without being a slope.
     *
     * @return list<Violation>
     */
    protected function violations(File $file): array
    {
        $violations = [];
        $runs = $this->commentRuns($file);

        if ($this->banners) {
            array_push($runs, ...$this->paragraphs($file));
        }

        foreach ($runs as $run) {
            if (
                count($run) < 2
                || $this->isSlope($run)
                || $this->isCode($run)
            ) {
                continue;
            }

            $violations[] = Violation::lines(array_key_first($run), array_key_last($run));
        }

        return $violations;
    }

    /**
     * Get the paragraphs of the block comments, each as its text after the pipe by line number.
     *
     * @return list<array<int, string>>
     */
    protected function paragraphs(File $file): array
    {
        $paragraphs = [];

        foreach ($file->tokens() as $token) {
            if ($token->id !== T_COMMENT || ! str_starts_with($token->text, '/*')) {
                continue;
            }

            $paragraph = [];
            $listing = false;

            foreach (explode("\n", $token->text) as $offset => $line) {
                $text = $this->pipedText($line);

                if ($text === null) {
                    $paragraphs[] = $paragraph;
                    $paragraph = [];
                    $listing = false;

                    continue;
                }

                $listing = $listing || preg_match(static::LIST, $text) === 1;

                if (! $listing) {
                    $paragraph[$token->line + $offset] = $text;
                }
            }

            $paragraphs[] = $paragraph;
        }

        return array_values(array_filter($paragraphs));
    }

    /**
     * Get the text after the pipe of a line of a banner, or null when the line is not one that continues a paragraph.
     */
    protected function pipedText(string $line): ?string
    {
        $line = ltrim($line);

        if (! str_starts_with($line, '|')) {
            return null;
        }

        $text = trim(substr($line, 1));

        return $text === '' || str_starts_with($text, '---') ? null : $text;
    }

    /**
     * Get the runs of // comments that each start their own line, on consecutive lines, each as its text by line number.
     *
     * @return list<array<int, string>>
     */
    protected function commentRuns(File $file): array
    {
        $runs = [];
        $run = [];
        $codeEnd = 0;

        foreach ($file->tokens() as $token) {
            if (! in_array($token->id, [T_COMMENT, T_DOC_COMMENT, T_WHITESPACE], true)) {
                $codeEnd = $token->line + substr_count(rtrim($token->text), "\n");
            }

            if (
                $token->id !== T_COMMENT
                || ! str_starts_with($token->text, '//')
                || $codeEnd >= $token->line
            ) {
                continue;
            }

            if ($run !== [] && ! isset($run[$token->line - 1])) {
                $runs[] = $run;
                $run = [];
            }

            $run[$token->line] = trim(substr($token->text, 2));
        }

        if ($run !== []) {
            $runs[] = $run;
        }

        return $runs;
    }

    /**
     * Determine if the run has three lines, each 2 to 4 characters shorter than the one above.
     *
     * @param  array<int, string>  $run
     */
    protected function isSlope(array $run): bool
    {
        $lengths = array_map(mb_strlen(...), array_values($run));

        return count($lengths) === 3
            && in_array($lengths[0] - $lengths[1], [2, 3, 4], true)
            && in_array($lengths[1] - $lengths[2], [2, 3, 4], true);
    }

    /**
     * Determine if the run holds commented-out code.
     *
     * @param  array<int, string>  $run
     */
    protected function isCode(array $run): bool
    {
        return array_filter($run, fn (string $text) => preg_match(static::CODE, $text) === 1) !== [];
    }
}
