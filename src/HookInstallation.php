<?php

namespace ClaudioDekker\LaravelGuardrails;

/**
 * @internal
 */
enum HookInstallation
{
    /**
     * A hook the package did not write is in place, and stays.
     */
    case FOREIGN;

    /**
     * The hook was written for the first time.
     */
    case INSTALLED;

    /**
     * The hook was already in place as written.
     */
    case UNCHANGED;

    /**
     * The package's own hook was rewritten.
     */
    case UPDATED;

    /**
     * Get the line to tell the developer, which is empty when nothing changed.
     */
    public function message(HookInstaller $installer): string
    {
        return match ($this) {
            self::FOREIGN => "A pre-commit hook already exists at {$installer->path}. Add \"{$installer->command()} || exit 1\" to it to run the guardrails before each commit.",
            self::INSTALLED => "Installed the pre-commit hook at {$installer->path}.",
            self::UNCHANGED => '',
            self::UPDATED => "Updated the pre-commit hook at {$installer->path}.",
        };
    }
}
