<?php

use ClaudioDekker\LaravelGuardrails\HookInstallation;
use ClaudioDekker\LaravelGuardrails\HookInstaller;
use ClaudioDekker\LaravelGuardrails\Repository;
use ClaudioDekker\LaravelGuardrails\Tests\Support\ScratchRepository;

it('writes an executable pre-commit hook into the repository and says so', function () {
    ScratchRepository::using(function (ScratchRepository $repository) {
        $installer = HookInstaller::for(new Repository($repository->path));

        $outcome = $installer->install();

        expect($outcome)->toBe(HookInstallation::INSTALLED)
            ->and($installer->path)->toBe($repository->path.'/.git/hooks/pre-commit')
            ->and(file_get_contents($installer->path))->toContain('exec vendor/bin/guardrails --staged')
            ->and(is_executable($installer->path))->toBeTrue()
            ->and($outcome->message($installer))->toBe("Installed the pre-commit hook at {$installer->path}.");
    });
});

it('leaves a hook it did not write alone and says how to call the guardrails from it', function () {
    ScratchRepository::using(function (ScratchRepository $repository) {
        $repository->write('.git/hooks/pre-commit', "#!/bin/sh\nphp-cs-fixer fix\n");
        $installer = HookInstaller::for(new Repository($repository->path));

        $outcome = $installer->install();

        expect($outcome)->toBe(HookInstallation::FOREIGN)
            ->and(file_get_contents($installer->path))->toBe("#!/bin/sh\nphp-cs-fixer fix\n")
            ->and($outcome->message($installer))
            ->toBe("A pre-commit hook already exists at {$installer->path}. Add \"vendor/bin/guardrails --staged || exit 1\" to it to run the guardrails before each commit.");
    });
});

it('says how to call the guardrails of a project in a subfolder from a hook it did not write', function () {
    ScratchRepository::using(function (ScratchRepository $repository) {
        $repository->write('.git/hooks/pre-commit', "#!/bin/sh\nphp-cs-fixer fix\n");
        $installer = HookInstaller::for(new Repository($repository->directory('my backend')->write('composer.json', '{}')->path));

        $outcome = $installer->install();

        expect($outcome->message($installer))
            ->toBe("A pre-commit hook already exists at {$installer->path}. Add \"(cd 'my backend' && vendor/bin/guardrails --staged) || exit 1\" to it to run the guardrails before each commit.");
    });
});

it('changes nothing on a second install', function () {
    ScratchRepository::using(function (ScratchRepository $repository) {
        $installer = HookInstaller::for(new Repository($repository->path));
        $installer->install();
        touch($installer->path, 1_000_000_000);

        $outcome = $installer->install();

        expect($outcome)->toBe(HookInstallation::UNCHANGED)
            ->and($outcome->message($installer))->toBe('')
            ->and(filemtime($installer->path))->toBe(1_000_000_000);
    });
});

it('rewrites its own hook when it differs from the current one', function () {
    ScratchRepository::using(function (ScratchRepository $repository) {
        $repository->write('.git/hooks/pre-commit', "#!/bin/sh\n# Installed by claudiodekker/laravel-guardrails.\nexec vendor/bin/guardrails\n");
        $installer = HookInstaller::for(new Repository($repository->path));

        $outcome = $installer->install();

        expect($outcome)->toBe(HookInstallation::UPDATED)
            ->and(file_get_contents($installer->path))->toContain('exec vendor/bin/guardrails --staged')
            ->and(is_executable($installer->path))->toBeTrue();
    });
});

it('makes its own hook executable again', function () {
    ScratchRepository::using(function (ScratchRepository $repository) {
        $installer = HookInstaller::for(new Repository($repository->path));
        $installer->install();
        chmod($installer->path, 0644);

        $outcome = $installer->install();

        expect($outcome)->toBe(HookInstallation::UPDATED)
            ->and(is_executable($installer->path))->toBeTrue();
    });
});

it('installs into the hooks directory the worktrees share when run from a linked worktree', function () {
    ScratchRepository::using(function (ScratchRepository $repository) {
        $repository->commitWithoutHook();
        $worktree = $repository->worktree('feature');
        $installer = HookInstaller::for(new Repository($worktree->path));

        $outcome = $installer->install();

        expect($outcome)->toBe(HookInstallation::INSTALLED)
            ->and($installer->path)->toBe($repository->path.'/.git/hooks/pre-commit')
            ->and(is_file($installer->path))->toBeTrue();
    });
});

it('honours a custom core.hooksPath and creates the directory', function () {
    ScratchRepository::using(function (ScratchRepository $repository) {
        $repository->git('config', 'core.hooksPath', '.githooks');
        $installer = HookInstaller::for(new Repository($repository->path));

        $outcome = $installer->install();

        expect($outcome)->toBe(HookInstallation::INSTALLED)
            ->and($installer->path)->toBe($repository->path.'/.githooks/pre-commit')
            ->and(is_executable($installer->path))->toBeTrue();
    });
});

it('removes its own hook on uninstall', function () {
    ScratchRepository::using(function (ScratchRepository $repository) {
        $installer = HookInstaller::for(new Repository($repository->path));
        $installer->install();

        $removed = $installer->uninstall();

        expect($removed)->toBeTrue()
            ->and(is_file($installer->path))->toBeFalse();
    });
});

it('leaves a hook it did not write alone on uninstall', function () {
    ScratchRepository::using(function (ScratchRepository $repository) {
        $repository->write('.git/hooks/pre-commit', "#!/bin/sh\nphp-cs-fixer fix\n");
        $installer = HookInstaller::for(new Repository($repository->path));

        $removed = $installer->uninstall();

        expect($removed)->toBeFalse()
            ->and(file_get_contents($installer->path))->toBe("#!/bin/sh\nphp-cs-fixer fix\n");
    });
});

it('reports nothing to remove when no hook is in place', function () {
    ScratchRepository::using(function (ScratchRepository $repository) {
        $installer = HookInstaller::for(new Repository($repository->path));

        expect($installer->uninstall())->toBeFalse();
    });
});
