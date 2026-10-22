# Write a check

A check is a class that extends `ClaudioDekker\LaravelGuardrails\Check`. It gives a one-line description, a message that names the fix, and the violations of a file: the places that break the rule. You list it in `guardrails.php` like any built-in check. `Check` comes from a dev dependency, so keep your checks under a namespace `autoload-dev` maps, such as `Tests\Guardrails` in `tests/Guardrails`.

This check refuses `date()` and `time()` in the source code and the config, so tests can freeze the clock:

```php
<?php

namespace Tests\Guardrails;

use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Violation;
use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;

class NoDateFunctions extends Check
{
    /**
     * Get the one-line description of the rule, shown when the checks are chosen.
     */
    public function description(): string
    {
        return 'No date() or time() in the source code or the config.';
    }

    /**
     * Get the explanation of the rule, shown under the checks when they are chosen.
     */
    public function explanation(): string
    {
        return 'date() and time() read the system clock, so a test cannot freeze them with $this->travelTo(). This flags every call in the source code and the config, except in a Console folder. Call now() instead, for example now()->timestamp.';
    }

    /**
     * Get the kinds of code the check reads.
     *
     * @return list<Kind>
     */
    public function kinds(): array
    {
        return [Kind::SOURCE, Kind::CONFIG];
    }

    /**
     * Get the one-line description of the files the check reads.
     */
    public function scope(): string
    {
        return 'PHP in source code and config, outside Console folders';
    }

    /**
     * Get the failure message, naming the fix.
     */
    public function message(): string
    {
        return 'Use now() instead of date() or time(), so tests can freeze the clock.';
    }

    /**
     * Determine if the check reads the file, which is any file outside a Console folder.
     */
    protected function reads(File $file): bool
    {
        return parent::reads($file) && ! $file->isIn('Console');
    }

    /**
     * Get a violation at each call to date() or time(), naming the function to replace.
     *
     * @return list<Violation>
     */
    protected function violations(File $file): array
    {
        $calls = array_filter($file->findInstanceOf(FuncCall::class), fn (FuncCall $call) => $this->isDateFunction($call));

        return array_map(fn (FuncCall $call) => Violation::at($call->name, "Replace {$call->name}() with now()."), array_values($calls));
    }

    /**
     * Determine if the call is to date() or time().
     */
    protected function isDateFunction(FuncCall $call): bool
    {
        return $call->name instanceof Node\Name
            && in_array($call->name->toLowerString(), ['date', 'time'], true);
    }
}
```

The sections below explain each part.

## Declare the kinds

`kinds()` returns the kinds of code the check reads, as cases of the `Kind` enum:

- `SOURCE` is the code the project autoloads.
- `TESTS` is the tests.
- `CONFIG` is the config files.
- `MIGRATIONS` is the migrations.
- `ROUTES` is the routes.
- `VIEWS` is the views.
- `FRONTEND` is the JavaScript and CSS under `resources`.
- `LANG` is the translations.
- `TEXT` is every file not under another kind, such as the docs.

The base class reads every kind, and `Kind::except(Kind::TESTS)` gives every kind but the ones you name.

A new kind is only ever carved out of `TEXT`, in a minor release. A check that lists `TEXT` also reads every kind carved out of it later, so it never loses a file to a new kind. `Kind::TEXT->includes($kind)` answers that in one place, and the base class asks it. A check that lists the other kinds reads only those. Do not `match` on a `Kind` without a default arm, because a case may be added.

The command finds the folders of each kind, as [Kinds of code](how-it-works.md#kinds-of-code) describes, so a check never names a folder.

## Choose the files by name

`files()` returns `fnmatch` patterns on the file's name, and the check reads a file when one of them matches. The base class returns `['*.php']`. A check of every file returns `['*']`, and a check of the tests may return `['*Test.php']`. This check reads the Vue and TypeScript files of the frontend code:

```php
/**
 * Get the kinds of code the check reads.
 *
 * @return list<Kind>
 */
public function kinds(): array
{
    return [Kind::FRONTEND];
}

/**
 * Get the patterns of the names of the files the check reads.
 *
 * @return list<string>
 */
protected function files(): array
{
    return ['*.vue', '*.ts'];
}
```

The picker and `guardrails list` show what the check reads from the two, as `*.vue, *.ts in frontend code`.

A pattern matches the file's name alone, never its folders, so `config/*.php` can match nothing. The run refuses such a pattern before it reads any file, and exits with 2. To read the config files, declare `Kind::CONFIG` in `kinds()`. To read one folder only, limit the check with `only()` in `guardrails.php`.

## Narrow within the kinds

To read only some files of its kinds, override `reads(File $file)` and add a condition to the base one, as `NoDateFunctions` does to skip `Console` folders. `File::isIn('Console')` is true when a folder of that name is anywhere in the file's path. A rule about `Concerns` folders or `Unit` tests then holds in an app and in each package of a monorepo alike.

Generated files, binary files, lockfiles and files under a `vendor` folder never reach `reads()`.

A check that narrows what it reads overrides `scope()` to say so.

## Write the explanation

The explanation shows under the tree in `guardrails install` while the check is focused, and under the check's row in `guardrails list --details`. Write two to four plain sentences: what the check flags, what goes wrong without it, what it leaves alone, and the fix. Without one, the check shows its description.

## Write the message

`message()` names the fix, and applies to every violation that gives no message of its own. To name what was found, pass a message as the last argument of any `Violation` constructor, as `NoDateFunctions` does with `Replace date() with now().` A failure then reads:

```
NoDateFunctions
  app/Billing/Invoice.php:3 Replace date() with now().
```

A check whose violations each carry a message leaves `message()` out. A violation that has no message of its own in a check without `message()` is reported as a crash of the check.

A message may span several lines. The first line follows each `path:line`, and the rest of each distinct message prints once under the check's failures.

## Say where a violation is, and what makes it new

A violation has two parts. The first is where it is reported: the `path:line` a reader opens. The second is what makes it new: the lines a change has to add or change for the violation to count on a staged or branch run. A run over every file reports every violation.

Choose the constructor by what the violation is.

### A node that is the violation as a whole

`Violation::at($node)` reports the node at the line of its name when it has one, as a call or a declaration does, and at its first line otherwise. A change to any line of the node brings it in, its attributes included and the comment above it left out. `NoConfigDefaults` uses it, because the default is the violation and may sit on a line of its own:

```php
$limit = config(
    'app.limit',
    5,
);
```

A change that adds the line with `5` brings the violation in, reported at the line of `config(`:

```php
return array_map(Violation::at(...), $file->find(fn (Node $node) => $this->readsWithDefault($node)));
```

### A call that is forbidden whatever its arguments

Pass the name of the call instead of the call, so a change to an argument on another line leaves the violation alone. `NoWeakHashes` does this, because `md5()` is the violation and its argument is not:

```php
return array_map(fn (FuncCall $call) => Violation::at($call->name), array_values($calls));
```

`NoDateFunctions` does the same at the top of this page.

### A declaration judged by its head

`Violation::lines($first, $last)` reports a run of lines at the first and is brought in by a change to any of them. `DocumentedDeclarations` judges a method from its attributes through its name, where the docblock goes, and never by its body, so an edit inside the method does not ask for a docblock while deleting the docblock above it does. For a method alone, it comes down to:

```php
return array_map(fn (ClassMethod $node) => Violation::lines($node->getStartLine(), $node->name->getStartLine()), $undocumented);
```

`SlopedComments` uses `lines()` for a comment of several lines, so an edit to any line of the comment brings the violation in, reported at the first line.

### One line

`Violation::line($line)` reports one line and is brought in by a change to it. `NoEmDashes` uses it, because the line holds the whole violation.

### Two pieces of code that relate

A rule about the order of two things, or the space between them, reports the lower one and is new when a change touches either. `Violation::between($above, $below)` takes the node above and the node below, and either may be a comment. `BlankLineAfterMultilineStatements` passes the comment above the lower statement when there is one, so typing a comment into the gap is caught too. In short:

```php
$violations[] = Violation::between($statements[$index - 1], $next->getComments()[0] ?? $next);
```

`SortedConfigKeys` fails a key that sorts before the key above it, so editing the key above it can also bring the failure in:

```php
$unsorted[] = Violation::between($previous, $item->key, "Move '{$item->key->value}' above '{$previous->value}'.");
```

### The whole file

A rule about the file as a whole returns `[Violation::file()]` from `violations()`. It prints without a line number. To show a line all the same, pass it as `line:`, as `NoSyntaxErrors` does with the line of the parse error.

A whole-file violation counts as new unless the file before the change had a whole-file violation with the same message. So a check that asks every file for `declare(strict_types=1)` fails the change that removes it, and leaves alone an edit to a file that never had it.

The comparison is by message, so rewording the message of a whole-file violation makes the old violations count as new once, in the first change that touches each file.

To decide another way, override `isNewWholeFile(Violation $violation, File $file, File $previous)`, which receives the violation, the file, and the file as it was before the change. `FilesWithinTokenBudget` fails a file over the budget only when the change makes it larger:

```php
/**
 * Determine if the oversized file is new with the change, which is when the change makes it hold more tokens, wherever it moved.
 */
protected function isNewWholeFile(Violation $violation, File $file, File $previous): bool
{
    return $this->tokens($file) > $this->tokens($previous);
}
```

A file the change moves into the check's coverage, out of an allowed folder or into a path `only()` names, brings every violation in, as an added file does.

## Read the file

The `File` holds the `path` from the project root, the `contents`, the `kind`, and the `project`. A check reads another file through the project, the way `TestsInTestSuites` reads `phpunit.xml`. `read('phpunit.xml')` returns the contents or `null`. `files('packages/*/composer.json')` and `folders('packages/*')` list paths, and `devNamespaces()` returns the namespaces `autoload-dev` maps. They answer from the files git tracks at the revision of the checked file: the index with `--staged`, HEAD against a base, and the working tree with `--all`. The project holds dotfiles but no symlinks or submodules, and lists paths in sorted order. A file from `TestFile::of()` has no project, so they find nothing. `TestFile::in()` reads every file under the folder you pass it, tracked or not.

`statements()`, `tokens()`, `find()` and `findInstanceOf()` parse the file's PHP once, with every name resolved to its full name. `find()` takes a function that accepts a node, and `findInstanceOf()` takes a node class. A file that is not PHP, and a Blade template, hold no statements and no tokens. A text check reads their `contents` instead.

The parser targets the lowest PHP version that `require.php` in `composer.json` allows, and the PHP that runs the command without one. php-parser accepts the syntax of the newest PHP it supports whatever the target. The target changes only minor behavior: which keywords of newer PHP may be identifiers, which type names are builtin, and whether older constructs such as `$x =& new Foo` or an invalid octal are accepted.

The nodes a check receives are the node classes of [nikic/php-parser](https://github.com/nikic/PHP-Parser) `^5`. They are part of this package's contract, so a move to another major version of the parser is a breaking change here.

## Test your check

`Testing\TestFile` runs a check over a file you write in a test, and reports what a run would. [Test a check](testing-checks.md) shows how.

## Take an option

A check that needs an option takes it through its constructor, with the default as the parameter's default. Listing the class name runs the check with its defaults, and `new NoDateFunctions(...)` runs it configured. `FilesWithinTokenBudget` takes `limit` this way, with `5000` as the default:

```php
return [
    new FilesWithinTokenBudget(limit: 8000),
];
```

Name the option and its default in `explanation()`, so `guardrails list --details` tells a reader how to set it.

Add an option only when a check needs one.

## Forbid calls

If all you need is to forbid some calls, extend `ForbiddenCalls` instead and list them in `$functions`, `$methods` or `$staticCalls`. A failure names the call it found before your message, as `DB::table() is forbidden. Query through the model, because an app may rename the table.`:

```php
class NoTableAccess extends ForbiddenCalls
{
    protected array $staticCalls = [DB::class => ['table']];

    public function message(): string
    {
        return 'Query through the model, because an app may rename the table.';
    }
}
```

Without `message()`, a failure names the call alone, as `DB::table() is forbidden.`

## Use a built-in check in another folder

`NoHttpInUnitTests` reads the tests in a `Unit` folder. A subclass can set another folder, and a message of its own:

```php
class NoHttpInFeatureTests extends NoHttpInUnitTests
{
    /**
     * The test folder the rule holds in.
     */
    protected string $folder = 'Feature';

    /**
     * Get the failure message, naming the fix.
     */
    public function message(): string
    {
        return 'Write it as an acceptance test through the app, or allow the file with the reason it cannot be one.';
    }
}
```

## Know what a check may override

A check overrides `description()`, `explanation()`, `message()`, `kinds()`, `scope()`, `files()`, `reads()`, `violations()`, `violationsOf()` and `isNewWholeFile()`. A check that judges the change rather than the file overrides `violationsOf()`, as [Judge the change itself](judging-changes.md) shows. The rest belongs to the runner and the picker. `check()`, `messageOf()`, `ensurePatterns()`, `only()`, `allowing()`, `allowances()`, `coverage()`, `as()`, `label()`, `name()` and `fullName()` are marked `@final`, and PHPStan refuses an override.

Every method and property name on `Check` is reserved for the package. Later capabilities arrive as opt-in interfaces, so a check you write today never gains a member it did not ask for, but a public or protected member of your own can collide with one the package adds. Beyond its hooks, give a check only members that are clearly its own, and make a helper `private` or give it a name no capability would use.

## Add the check to `guardrails.php`

List the check by its class name, like any other check:

```php
return [
    NoDateFunctions::class,
];
```

Failures print under the check's class name. To list one class more than once, give each entry a name with `as()`, and its failures print under that name:

```php
return [
    (new NoNamespaces(['Acme\Billing']))->only('packages/crm/src')->as('NoBillingInCrm'),
    (new NoNamespaces(['Acme\Crm']))->only('packages/billing/src')->as('NoCrmInBilling'),
];
```

Two entries under one name refuse to run, and the error names both entries.

A check is known by its fully qualified class name, or by the name `as()` gives it. The short name is only for display, so two classes with one short name stay apart. The report, the allowances and the newness of a violation follow that identity, so renaming or moving the class, or changing its `as()` name, makes it a different check.

To offer the check in `guardrails install`, under your app's name, declare it in your app's `composer.json`:

```json
{
    "extra": {
        "guardrails": {
            "provides": {
                "checks": ["Tests\\Guardrails\\NoDateFunctions"]
            }
        }
    }
}
```

A check you list without declaring it runs all the same, and `guardrails list` shows it under `guardrails.php`.

## Allow files that do not parse

Every run fails a PHP file that does not parse under `NoSyntaxErrors`, whether or not `guardrails.php` lists it. To keep files that are broken on purpose, such as the fixtures of a parser, list the check with the folder allowed:

```php
return [
    NoDateFunctions::class,
    (new NoSyntaxErrors)->allowing(['tests/Fixtures/broken' => 'The parser tests read files that do not parse.']),
];
```

`NoSyntaxErrors` takes no `only()`, because it runs over every PHP file. An entry that limits it stops the run with exit 2.

## Publish a check pack

Any Composer package can offer checks and presets to the apps that require it. [Publish a check pack](check-packs.md) shows how.
