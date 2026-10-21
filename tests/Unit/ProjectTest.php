<?php

use ClaudioDekker\LaravelGuardrails\Git;
use ClaudioDekker\LaravelGuardrails\Project;
use ClaudioDekker\LaravelGuardrails\Tests\Support\ScratchRepository;

it('reads and lists the files of a folder on disk by their paths from the folder', function () {
    $project = Project::onDisk(projectWith([
        'composer.json' => '{"name": "acme/app"}',
        'packages/shop/composer.json' => '{"name": "acme/shop"}',
        'packages/shop/config/shop.php' => '<?php',
        'packages/blog/README.md' => '',
        'packages/blog/config' => '',
    ]));

    expect($project->read('composer.json'))->toBe('{"name": "acme/app"}')
        ->and($project->read('phpunit.xml'))->toBeNull()
        ->and($project->files('packages/*/composer.json'))->toBe(['packages/shop/composer.json'])
        ->and($project->files('packages/*/config'))->toBe(['packages/blog/config'])
        ->and($project->folders('packages/*/config'))->toBe(['packages/shop/config'])
        ->and($project->folders('packages/shop/config'))->toBe(['packages/shop/config'])
        ->and($project->folders('packages/missing'))->toBe([]);
});

it('reads and lists the files of the index and of a commit, from the project folder', function () {
    [$index, $head] = ScratchRepository::using(function (ScratchRepository $repository) {
        $repository
            ->write('phpunit.xml', '<root/>')
            ->write('backend/phpunit.xml', '<committed/>')
            ->write('backend/packages/shop/composer.json', '{}')
            ->write('backend/packages/shop/config/shop.php', '<?php')
            ->stageAll()
            ->commitWithoutHook()
            ->write('backend/phpunit.xml', '<staged/>')
            ->write('backend/packages/blog/composer.json', '{}')
            ->stageAll()
            ->write('backend/phpunit.xml', '<working/>');

        $git = new Git($repository->path.'/backend');
        $index = Project::atRevision($git, ':');
        $head = Project::atRevision($git, 'HEAD');

        return [
            [$index->read('phpunit.xml'), $index->read('composer.json'), $index->files('packages/*/composer.json'), $index->folders('packages/*/config')],
            [$head->read('phpunit.xml'), $head->files('packages/*/composer.json'), $head->folders('packages/*'), $head->folders('packages/shop/config')],
        ];
    });

    expect($index)->toBe(['<staged/>', null, ['packages/blog/composer.json', 'packages/shop/composer.json'], ['packages/shop/config']])
        ->and($head)->toBe(['<committed/>', ['packages/shop/composer.json'], ['packages/shop'], ['packages/shop/config']]);
});

it('lists the same files in the folder, the index and a commit: the regular files git tracks, dotfiles included', function () {
    $listings = ScratchRepository::using(function (ScratchRepository $repository) {
        $repository
            ->write('.editorconfig', "root = true\n")
            ->write('.github/workflows/ci.yml', "on: push\n")
            ->write('app/Real.php', "<?php\n")
            ->write('app/Link.php', '')
            ->link('app/Link.php', 'Real.php')
            ->write('bin/run', "#!/bin/sh\n");

        chmod($repository->path.'/bin/run', 0755);

        $repository
            ->stageAll()
            ->stageSubmodule('lib', str_repeat('1', 40))
            ->commitWithoutHook()
            ->write('notes.txt', "Not tracked.\n");

        $git = new Git($repository->path);

        return array_map(
            fn (Project $project) => [$project->files('*'), $project->files('app/*'), $project->files('bin/*'), $project->folders('.github/*')],
            [Project::inDirectory($repository->path), Project::atRevision($git, ':'), Project::atRevision($git, 'HEAD')],
        );
    });

    expect($listings)->toBe(array_fill(0, 3, [['.editorconfig'], ['app/Real.php'], ['bin/run'], ['.github/workflows']]));
});

it('names the revision that holds a composer.json that is not valid JSON', function () {
    [$messages, $commit] = ScratchRepository::using(function (ScratchRepository $repository) {
        $repository->write('composer.json', '{')->stageAll()->commitWithoutHook();

        $git = new Git($repository->path);
        $projects = [Project::inDirectory($repository->path), Project::atRevision($git, ':'), Project::atRevision($git, 'HEAD')];

        $messages = array_map(function (Project $project) {
            try {
                $project->manifest('composer.json');
            } catch (InvalidArgumentException $exception) {
                return $exception->getMessage();
            }

            return null;
        }, $projects);

        return [$messages, rtrim($repository->git('rev-parse', '--short', 'HEAD'))];
    });

    expect($messages)->toBe([
        'composer.json is not valid JSON: Syntax error',
        'the staged composer.json is not valid JSON: Syntax error',
        "composer.json at {$commit} is not valid JSON: Syntax error",
    ]);
});

it('reads nothing from nowhere', function () {
    $project = Project::nowhere();

    expect($project->read('composer.json'))->toBeNull()
        ->and($project->files('*'))->toBe([])
        ->and($project->folders('*'))->toBe([])
        ->and($project->phpUnit())->toBeNull();
});

it('resolves the layout and the PHPUnit configuration once', function () {
    $project = Project::onDisk(projectWith(['phpunit.xml' => '<phpunit><testsuites><testsuite name="Unit"><directory>tests/Unit</directory></testsuite></testsuites></phpunit>']));

    expect($project->layout())->toBe($project->layout())
        ->and($project->phpUnit())->toBe($project->phpUnit())
        ->and($project->phpUnit()?->name)->toBe('phpunit.xml');
});

it('lists the files of a folder given with a trailing slash by their paths from the folder', function () {
    $project = Project::onDisk(projectWith(['composer.json' => '{}', 'src/A.php' => '<?php']).'/');

    expect($project->files('*'))->toBe(['composer.json'])
        ->and($project->files('src/*'))->toBe(['src/A.php']);
});

it('lists a file by its name alone, and lists the same paths on every ask', function () {
    $project = Project::onDisk(projectWith(['composer.json' => '{}', 'config/app.php' => '<?php']));

    expect($project->files('composer.json'))->toBe(['composer.json'])
        ->and($project->files('missing.json'))->toBe([])
        ->and($project->files('config/*.php'))->toBe(['config/app.php'])
        ->and($project->files('config/*.php'))->toBe(['config/app.php'])
        ->and($project->read('config/app.php'))->toBe('<?php');
});

it('gives the namespaces autoload-dev maps', function () {
    $project = Project::onDisk(projectWith(['composer.json' => '{"autoload-dev": {"psr-4": {"Acme\\\\Tests\\\\": "tests/"}}}']));

    expect($project->devNamespaces())->toBe(['Acme\\Tests\\']);
});
