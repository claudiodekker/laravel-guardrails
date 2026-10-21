<?php

use ClaudioDekker\LaravelGuardrails\Plugin;
use ClaudioDekker\LaravelGuardrails\Tests\Support\ScratchRepository;
use Composer\Factory;
use Composer\IO\NullIO;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;

it('installs the hook when the project requires the package', function (string $section) {
    ScratchRepository::using(function (ScratchRepository $repository) use ($section) {
        $result = $repository
            ->writeComposerJson([$section => ['claudiodekker/laravel-guardrails' => '*']])
            ->composerInstall();

        expect($result->successful())->toBeTrue()
            ->and($result->output)->toContain("Installed the pre-commit hook at {$repository->hookPath()}.")
            ->and(is_executable($repository->hookPath()))->toBeTrue();
    });
})->with(['require', 'require-dev']);

it('points at the install command, on one line, while the project has no checks selected', function (array $manifest, int $lines) {
    ScratchRepository::using(function (ScratchRepository $repository) use ($manifest, $lines) {
        $result = $repository
            ->writeComposerJson(['require-dev' => ['claudiodekker/laravel-guardrails' => '*'], ...$manifest])
            ->composerInstall();

        expect($result->successful())->toBeTrue()
            ->and(substr_count($result->output, "No guardrails selected. Run vendor/bin/guardrails install to choose them.\n"))->toBe($lines);
    });
})->with([
    'no selection' => [[], 1],
]);

it('says nothing about the selection once guardrails.php selects the checks', function () {
    ScratchRepository::using(function (ScratchRepository $repository) {
        $result = $repository
            ->select()
            ->writeComposerJson(['require-dev' => ['claudiodekker/laravel-guardrails' => '*']])
            ->composerInstall();

        expect($result->successful())->toBeTrue()
            ->and($result->output)->not->toContain('No guardrails selected');
    });
});

it('says nothing about a selection that holds an instance of a check of the app itself, which only the app can load', function () {
    ScratchRepository::using(function (ScratchRepository $repository) {
        $result = $repository
            ->write('app/Guardrails/NoFoo.php', "<?php\n\nnamespace App\\Guardrails;\n\nuse ClaudioDekker\\LaravelGuardrails\\Tests\\Support\\EveryLine;\n\nclass NoFoo extends EveryLine\n{\n    //\n}\n")
            ->write('guardrails.php', "<?php\n\nreturn [\n    new App\\Guardrails\\NoFoo,\n];\n")
            ->writeComposerJson(['require-dev' => ['claudiodekker/laravel-guardrails' => '*'], 'autoload' => ['psr-4' => ['App\\' => 'app/']]])
            ->composerInstall();

        expect($result->successful())->toBeTrue()
            ->and($result->output)->not->toContain('could not be loaded')
            ->and($result->output)->not->toContain('No guardrails selected');
    });
});

it('leaves a project alone that only gets the package through another dependency', function () {
    ScratchRepository::using(function (ScratchRepository $repository) {
        $result = $repository
            ->write('toolkit/composer.json', '{"name": "acme/toolkit", "version": "1.0.0", "require": {"claudiodekker/laravel-guardrails": "*"}}')
            ->writeComposerJson(['require' => ['acme/toolkit' => '*']], ['toolkit'])
            ->composerInstall();

        expect($result->successful())->toBeTrue()
            ->and(is_file($repository->path.'/vendor/bin/guardrails'))->toBeTrue()
            ->and(is_file($repository->hookPath()))->toBeFalse()
            ->and($result->output)->not->toContain('No guardrails selected');
    });
});

it('leaves a project alone on CI', function (string $variable) {
    ScratchRepository::using(function (ScratchRepository $repository) use ($variable) {
        $result = $repository
            ->writeComposerJson(['require-dev' => ['claudiodekker/laravel-guardrails' => '*']])
            ->composerInstall($variable);

        expect($result->successful())->toBeTrue()
            ->and(is_file($repository->hookPath()))->toBeFalse()
            ->and($result->output)->not->toContain('No guardrails selected');
    });
})->with([
    'CI=true',
    'GITHUB_ACTIONS=true',
    'GITLAB_CI=true',
    'JENKINS_URL=https://jenkins.example.com/',
]);

it('installs the hook when the CI variable is empty', function () {
    ScratchRepository::using(function (ScratchRepository $repository) {
        $repository
            ->writeComposerJson(['require-dev' => ['claudiodekker/laravel-guardrails' => '*']])
            ->composerInstall('CI=');

        expect(is_file($repository->hookPath()))->toBeTrue();
    });
});

it('installs a hook that runs the checks of a project in a subfolder from that folder', function () {
    $result = ScratchRepository::using(function (ScratchRepository $repository) {
        $repository->directory('backend')
            ->select()
            ->writeComposerJson(['require-dev' => ['claudiodekker/laravel-guardrails' => '*']])
            ->composerInstall()
            ->throw();

        return $repository
            ->write('backend/README.md', "Fast \u{2014} and loose.\n")
            ->stage('backend/README.md')
            ->stage('backend/guardrails.php')
            ->commit();
    });

    expect($result->exitCode)->toBe(1)
        ->and($result->errorOutput)->toContain("NoEmDashes\n  README.md:1 Replace the em dash");
});

it('installs the hook for the package itself from its own install script', function () {
    ScratchRepository::using(function (ScratchRepository $repository) {
        $repository->write('composer.json', '{"name": "claudiodekker/laravel-guardrails"}');
        $composer = (new Factory)->createComposer(new NullIO, $repository->path.'/composer.json', true, $repository->path);
        $environment = [];

        foreach (['CI', 'GITHUB_ACTIONS', 'GITLAB_CI', 'JENKINS_URL'] as $variable) {
            $environment[$variable] = getenv($variable);
            putenv($variable);
        }

        try {
            Plugin::install(new Event(ScriptEvents::POST_INSTALL_CMD, $composer, new NullIO));
        } finally {
            foreach ($environment as $variable => $value) {
                putenv($value === false ? $variable : "{$variable}={$value}");
            }
        }

        expect(is_file($repository->hookPath()))->toBeTrue();
    });
});
