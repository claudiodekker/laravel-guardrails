<?php

use ClaudioDekker\LaravelGuardrails\Project;

it('refuses a phpunit.xml that is not valid XML, naming it and the error, and leaves the libxml settings as it found them', function () {
    $project = Project::onDisk(projectWith(['phpunit.xml' => '<phpunit></testsuites>']));

    expect(fn () => $project->phpUnit())->toThrow(InvalidArgumentException::class, 'phpunit.xml is not valid XML: Opening and ending tag mismatch: phpunit line 1 and testsuites (line 1).')
        ->and(libxml_use_internal_errors())->toBeFalse()
        ->and(libxml_get_errors())->toBe([]);
});

it('refuses an empty phpunit.xml.dist', function () {
    $project = Project::onDisk(projectWith(['phpunit.xml.dist' => "\n"]));

    expect(fn () => $project->phpUnit())->toThrow(InvalidArgumentException::class, 'phpunit.xml.dist is not valid XML: the file is empty.');
});

it('names the first error of a phpunit.xml and its line, not the errors that follow from it', function () {
    $project = Project::onDisk(projectWith(['phpunit.xml' => "<phpunit>\n    <testsuites>\n        <testsuite name=\"Unit\">\n    </testsuites>\n</phpunit>\n"]));

    expect(fn () => $project->phpUnit())->toThrow(InvalidArgumentException::class, 'phpunit.xml is not valid XML: Opening and ending tag mismatch: testsuite line 3 and testsuites (line 4).');
});
