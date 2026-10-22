# Judge the change itself

Some rules are about the change rather than the file it leaves. A committed migration, for example, must never be edited, moved or deleted, because it may already have run. A check of such a rule overrides `violationsOf(Change $change)`, which receives the change. It still implements `violations()`, which returns `[]` here:

```php
<?php

namespace Tests\Guardrails;

use ClaudioDekker\LaravelGuardrails\Change;
use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Violation;

class NoEditedMigrations extends Check
{
    /**
     * Get the one-line description of the rule, shown when the checks are chosen.
     */
    public function description(): string
    {
        return 'A committed migration is never edited, moved or deleted.';
    }

    /**
     * Get the failure message, naming the fix.
     */
    public function message(): string
    {
        return 'Add a new migration instead, because this one may already have run.';
    }

    /**
     * Get the kinds of code the check reads.
     *
     * @return list<Kind>
     */
    public function kinds(): array
    {
        return [Kind::MIGRATIONS];
    }

    /**
     * Get no violations of the file alone, because the rule is about the change.
     *
     * @return list<Violation>
     */
    protected function violations(File $file): array
    {
        return [];
    }

    /**
     * Get a whole-file violation for every change to a migration except the one that adds it.
     *
     * @return list<Violation>
     */
    protected function violationsOf(Change $change): array
    {
        return $change->isAdded() ? [] : [Violation::file()];
    }
}
```

The change tells what happened to the file:

- `isAdded()` is true when the change adds the file.
- `isModified()` is true when the change edits the file's contents, whether or not it also moves the file.
- `isMoved()` is true when the change moves the file to another path, whether or not it also edits it.
- `isDeleted()` is true when the change deletes the file.
- `file` is the file the change leaves, and `null` when the change deletes it.
- `previous` is the file before the change, and `null` when the change adds it.

Unless a check overrides it, `violationsOf()` returns the violations of the file the change leaves, and none for a deletion. A deletion therefore reaches only a check that overrides `violationsOf()` and reads the deleted file. Every violation the check returns for a deletion counts. A file moved out of the kinds the check reads, such as a migration moved into `app`, reaches it as a deletion of the file before the move. If `previous` does not parse, the check gets the change again as an addition.

For any other change, the violations `violationsOf()` returns count as new the same way as those of `violations()`. A violation of lines counts when the change touches one of them, and a whole-file violation counts unless the file before the change had one with the same message. `NoEditedMigrations` returns `[]` from `violations()`, so the file before the change never has the violation, and every edit or move brings it in. During a merge, the check judges the change against each parent, and a violation counts only when it is new against every parent.
