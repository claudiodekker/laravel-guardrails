<?php

namespace ClaudioDekker\LaravelGuardrails\Checks\Php\Safety\Concerns;

use ClaudioDekker\LaravelGuardrails\File;

/**
 * @internal
 */
trait SkipsFakeData
{
    /**
     * Get the one-line description of the files the check reads, which leaves out the factories and seeders.
     */
    public function scope(): string
    {
        return parent::scope().', database/factories and database/seeders';
    }

    /**
     * Determine if the check reads the file, which is not in a factories or seeders folder of a database folder, because those make fake data.
     */
    protected function reads(File $file): bool
    {
        return parent::reads($file) && preg_match('#(^|/)database/(factories|seeders)/#', $file->path) !== 1;
    }
}
