<?php

use ClaudioDekker\LaravelGuardrails\Checks\Php\Comments\DocumentedDeclarations;
use ClaudioDekker\LaravelGuardrails\Checks\Php\Comments\PhpstanIgnoresNameIdentifier;
use ClaudioDekker\LaravelGuardrails\Checks\Php\Comments\SingleCaveatDocblocks;
use ClaudioDekker\LaravelGuardrails\Checks\Php\Comments\SlopedComments;
use ClaudioDekker\LaravelGuardrails\Checks\Php\Config\NoEnvOutsideConfig;
use ClaudioDekker\LaravelGuardrails\Checks\Php\Enums\EnumCasesDocumentedAlike;
use ClaudioDekker\LaravelGuardrails\Checks\Php\Enums\SortedEnumCases;
use ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\NoDebugCalls;
use ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\NoEval;
use ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\NoUnserialize;
use ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\NoWeakHashes;
use ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\NoWeakRandomness;
use ClaudioDekker\LaravelGuardrails\Checks\Php\Shape\BlankLineAfterMultilineStatements;
use ClaudioDekker\LaravelGuardrails\Checks\Php\Shape\NoBlankLineOpeningBlocks;
use ClaudioDekker\LaravelGuardrails\Checks\Php\Shape\NoMatchOnCall;
use ClaudioDekker\LaravelGuardrails\Checks\Php\Shape\NoNewThenCall;
use ClaudioDekker\LaravelGuardrails\Checks\Php\Shape\OneClausePerLine;
use ClaudioDekker\LaravelGuardrails\Checks\Php\Structure\ConcernsHoldOnlyTraits;
use ClaudioDekker\LaravelGuardrails\Checks\Php\Structure\NoFinal;
use ClaudioDekker\LaravelGuardrails\Checks\Php\Structure\OneClassPerFile;
use ClaudioDekker\LaravelGuardrails\Checks\Php\Tests\NoDocblocksInTests;
use ClaudioDekker\LaravelGuardrails\Checks\Php\Tests\NoFunctionsInTests;
use ClaudioDekker\LaravelGuardrails\Checks\Php\Tests\TestsInTestSuites;
use ClaudioDekker\LaravelGuardrails\Checks\Text\FilesWithinTokenBudget;
use ClaudioDekker\LaravelGuardrails\Checks\Text\NoEmDashes;

return [
    FilesWithinTokenBudget::class,
    NoEmDashes::class,
    NoDocblocksInTests::class,
    NoFunctionsInTests::class,
    TestsInTestSuites::class,
    OneClassPerFile::class,
    ConcernsHoldOnlyTraits::class,
    (new NoFinal)->allowing(['src/Violation.php' => 'A violation is a value the runner compares and prints, so nothing extends it.']),
    SortedEnumCases::class,
    EnumCasesDocumentedAlike::class,
    NoDebugCalls::class,
    NoEval::class,
    NoUnserialize::class,
    NoWeakHashes::class,
    NoWeakRandomness::class,
    NoMatchOnCall::class,
    NoNewThenCall::class,
    OneClausePerLine::class,
    NoBlankLineOpeningBlocks::class,
    BlankLineAfterMultilineStatements::class,
    SlopedComments::class,
    SingleCaveatDocblocks::class,
    DocumentedDeclarations::class,
    PhpstanIgnoresNameIdentifier::class,
    NoEnvOutsideConfig::class,
];
