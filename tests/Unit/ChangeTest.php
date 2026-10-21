<?php

use ClaudioDekker\LaravelGuardrails\Change;
use ClaudioDekker\LaravelGuardrails\File;
use ClaudioDekker\LaravelGuardrails\Kind;
use ClaudioDekker\LaravelGuardrails\Project;

it('tells what it did to the file, and at which path', function (Change $change, array $did, string $path) {
    $predicates = [
        'added' => $change->isAdded(),
        'deleted' => $change->isDeleted(),
        'moved' => $change->isMoved(),
        'modified' => $change->isModified(),
    ];

    expect($predicates)->toBe($did)
        ->and($change->path())->toBe($path);
})->with([
    'an added file' => [
        fn () => Change::added(new File('app/A.php', "one\n", Kind::SOURCE, Project::nowhere())),
        ['added' => true, 'deleted' => false, 'moved' => false, 'modified' => false],
        'app/A.php',
    ],
    'a deleted file' => [
        fn () => Change::deleted(new File('app/A.php', "one\n", Kind::SOURCE, Project::nowhere())),
        ['added' => false, 'deleted' => true, 'moved' => false, 'modified' => false],
        'app/A.php',
    ],
    'an edited file' => [
        fn () => Change::from(new File('app/A.php', "one\n", Kind::SOURCE, Project::nowhere()), new File('app/A.php', "two\n", Kind::SOURCE, Project::nowhere()), [1]),
        ['added' => false, 'deleted' => false, 'moved' => false, 'modified' => true],
        'app/A.php',
    ],
    'a moved file' => [
        fn () => Change::from(new File('app/Old.php', "one\n", Kind::SOURCE, Project::nowhere()), new File('app/A.php', "one\n", Kind::SOURCE, Project::nowhere()), []),
        ['added' => false, 'deleted' => false, 'moved' => true, 'modified' => false],
        'app/A.php',
    ],
    'a moved and edited file' => [
        fn () => Change::from(new File('app/Old.php', "one\n", Kind::SOURCE, Project::nowhere()), new File('app/A.php', "two\n", Kind::SOURCE, Project::nowhere()), [1]),
        ['added' => false, 'deleted' => false, 'moved' => true, 'modified' => true],
        'app/A.php',
    ],
    'a file whose contents and path stayed as they were' => [
        fn () => Change::from(new File('app/A.php', "one\n", Kind::SOURCE, Project::nowhere()), new File('app/A.php', "one\n", Kind::SOURCE, Project::nowhere()), []),
        ['added' => false, 'deleted' => false, 'moved' => false, 'modified' => false],
        'app/A.php',
    ],
]);
