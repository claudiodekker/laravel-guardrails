<?php

use ClaudioDekker\LaravelGuardrails\Change;
use ClaudioDekker\LaravelGuardrails\Check;
use ClaudioDekker\LaravelGuardrails\Checks\Php\Tests\TestsInUnitOrFeature;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Project;
use ClaudioDekker\LaravelGuardrails\Testing\TestFile;
use ClaudioDekker\LaravelGuardrails\Tests\Support\EveryLine;
use ClaudioDekker\LaravelGuardrails\Violation;

it('brings in a whole-file violation unless the file before the change had one with the same message', function (string $before, string $after, array $violations) {
    $rule = new class extends Check
    {
        public function description(): string
        {
            return 'Every PHP file declares strict types and a namespace.';
        }

        protected function violations(File $file): array
        {
            $violations = [];

            if (! str_contains($file->contents, 'declare(strict_types=1);')) {
                $violations[] = Violation::file('Declare strict types.');
            }

            if (! str_contains($file->contents, 'namespace ')) {
                $violations[] = Violation::file('Add a namespace.');
            }

            return $violations;
        }
    };

    $file = TestFile::of(Kind::SOURCE, 'app/A.php', $after)->changedFrom($before);

    expect($file->reports($rule))->toEqual($violations);
})->with([
    'a change that removes the declaration' => [
        "<?php\n\ndeclare(strict_types=1);\n\nnamespace App;\n\nclass A {}\n",
        "<?php\n\nnamespace App;\n\nclass A {}\n",
        [['line' => null, 'message' => 'Declare strict types.']],
    ],
    'a change to a file that already lacked it' => [
        "<?php\n\nnamespace App;\n\nclass A {}\n",
        "<?php\n\nnamespace App;\n\nclass A\n{\n}\n",
        [],
    ],
    'a change that removes it from a file that already broke the rule another way' => [
        "<?php\n\ndeclare(strict_types=1);\n\nclass A {}\n",
        "<?php\n\nclass A {}\n",
        [['line' => null, 'message' => 'Declare strict types.']],
    ],
]);

it('brings a violation of a merge in only when it is new against every parent', function () {
    $file = new File('app/a.php', "<?php\n\n\$a = 5;\ndd(\$a);\n\$b = 1;\n", Kind::SOURCE, Project::nowhere());
    $ours = Change::from(new File('app/a.php', "<?php\n\n\$a = 1;\n", Kind::SOURCE, Project::nowhere()), $file, [3, 4, 5]);
    $theirs = Change::from(new File('app/a.php', "<?php\n\n\$a = 3;\n\$b = 1;\n", Kind::SOURCE, Project::nowhere()), $file, [3, 4]);

    expect((new EveryLine)->check($ours, $theirs))->toEqual([Violation::line(3), Violation::line(4)]);
});

it('brings a whole-file violation of a merge in only when it is new against every parent', function (string $theirPath, array $violations) {
    $file = new File('tests/Other/FooTest.php', "<?php\n", Kind::TESTS, Project::nowhere());
    $ours = Change::from(new File('tests/Feature/FooTest.php', "<?php\n", Kind::TESTS, Project::nowhere()), $file, []);
    $theirs = Change::from(new File($theirPath, "<?php\n", Kind::TESTS, Project::nowhere()), $file, []);

    expect((new TestsInUnitOrFeature)->check($ours, $theirs))->toEqual($violations);
})->with([
    'a parent that kept the test in a Unit folder' => ['tests/Unit/FooTest.php', [Violation::file()]],
    'a parent that already had it outside one' => ['tests/Other/FooTest.php', []],
]);

it('lets a check judge the change itself, as one that fails an edit, a move and a deletion of a committed migration', function () {
    $rule = new class extends Check
    {
        public function description(): string
        {
            return 'A committed migration is never edited, moved or deleted.';
        }

        public function message(): string
        {
            return 'Add a new migration instead, because this one may already have run.';
        }

        public function kinds(): array
        {
            return [Kind::MIGRATIONS];
        }

        protected function violations(File $file): array
        {
            return [];
        }

        protected function violationsOf(Change $change): array
        {
            return $change->isAdded() ? [] : [Violation::file()];
        }
    };

    $migration = TestFile::of(Kind::MIGRATIONS, 'database/migrations/2026_01_01_000000_create_users_table.php', "<?php\n\nreturn 2;\n");

    expect(array_column($migration->changedFrom("<?php\n\nreturn 1;\n")->reports($rule), 'line'))->toBe([null])
        ->and(array_column($migration->movedFrom('database/migrations/2025_01_01_000000_create_users_table.php')->reports($rule), 'line'))->toBe([null])
        ->and(array_column($migration->deleted()->reports($rule), 'line'))->toBe([null])
        ->and($migration->reports($rule))->toBe([]);
});

it('reports nothing of a file the change deletes to a check that judges files, not changes', function () {
    $file = TestFile::of(Kind::TEXT, 'README.md', "Fast \u{2014} and loose.\n")->deleted();

    expect($file->reports(new EveryLine))->toBe([]);
});

it('judges a change as one that adds the file when a hook parses the previous file and it does not parse', function () {
    $rule = new class extends Check
    {
        public function description(): string
        {
            return 'Every file breaks the rule.';
        }

        public function message(): string
        {
            return 'Fix it.';
        }

        protected function violations(File $file): array
        {
            return [Violation::line(1)];
        }

        protected function violationsOf(Change $change): array
        {
            $change->previous?->statements();

            return $change->file === null ? [] : $this->violations($change->file);
        }
    };

    $repair = TestFile::of(Kind::SOURCE, 'app/A.php', "<?php\n\nclass A {}\n")->changedFrom("<?php\n\n\$a = ;\n");

    expect(array_column($repair->reports($rule), 'line'))->toBe([1]);
});

it('hands a check the deletion of a file that moves out of the kinds it reads', function () {
    $rule = new class extends Check
    {
        public function description(): string
        {
            return 'A committed migration is never edited, moved or deleted.';
        }

        public function message(): string
        {
            return 'Add a new migration instead, because this one may already have run.';
        }

        public function kinds(): array
        {
            return [Kind::MIGRATIONS];
        }

        protected function violations(File $file): array
        {
            return [];
        }

        protected function violationsOf(Change $change): array
        {
            return $change->isAdded() ? [] : [Violation::file()];
        }
    };

    $moved = TestFile::of(Kind::SOURCE, 'app/CreateUsersTable.php', "<?php\n\nreturn 1;\n")->movedFrom('database/migrations/2026_01_01_000000_create_users_table.php', Kind::MIGRATIONS);

    expect(array_column($moved->reports($rule), 'line'))->toBe([null])
        ->and(TestFile::of(Kind::SOURCE, 'app/CreateUsersTable.php', "<?php\n\nreturn 1;\n")->movedFrom('app/Old.php', Kind::SOURCE)->reports($rule))->toBe([]);
});
