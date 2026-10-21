<?php

namespace ClaudioDekker\LaravelGuardrails;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use InvalidArgumentException;
use RuntimeException;

/**
 * @internal
 */
class Plugin implements EventSubscriberInterface, PluginInterface
{
    /**
     * The package's name.
     */
    protected const PACKAGE = 'claudiodekker/laravel-guardrails';

    /**
     * The Composer instance running the install.
     */
    protected Composer $composer;

    /**
     * The console the install writes to.
     */
    protected IOInterface $io;

    /**
     * Keep the Composer instance and console for the install events.
     */
    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    /**
     * Do nothing when the plugin is turned off, so that the hook keeps working until the package is removed.
     */
    public function deactivate(Composer $composer, IOInterface $io): void
    {
        //
    }

    /**
     * Remove the hook the package wrote when the package is removed.
     */
    public function uninstall(Composer $composer, IOInterface $io): void
    {
        $installer = $this->installer();

        if ($installer !== null && $installer->uninstall()) {
            $io->write("Removed the pre-commit hook at {$installer->path}.");
        }
    }

    /**
     * Get the install events the plugin handles.
     *
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            ScriptEvents::POST_INSTALL_CMD => 'installHook',
            ScriptEvents::POST_UPDATE_CMD => 'installHook',
        ];
    }

    /**
     * Put the pre-commit hook in place from a Composer script, for a project that is the package itself.
     */
    public static function install(Event $event): void
    {
        $plugin = new self;
        $plugin->activate($event->getComposer(), $event->getIO());
        $plugin->installHook();
    }

    /**
     * Put the pre-commit hook in place after an install or update of a project that wants it, and point at the install command while it has no checks selected.
     */
    public function installHook(): void
    {
        if (! $this->wanted()) {
            return;
        }

        $installer = $this->installer();

        if ($installer !== null) {
            $message = $installer->install()->message($installer);

            if ($message !== '') {
                $this->io->write($message);
            }
        }

        if (! is_file($this->project().'/'.ConfigurationFile::NAME) || $this->selectsNothing()) {
            $this->io->write(Configuration::HINT);
        }
    }

    /**
     * Determine if the project's guardrails.php lists no check, read without running it, because Composer's process cannot load the project's own classes.
     */
    protected function selectsNothing(): bool
    {
        try {
            return ConfigurationFile::read($this->project())->classes() === [];
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    /**
     * Determine if the hook belongs in this install.
     */
    protected function wanted(): bool
    {
        if (Environment::isCi()) {
            return false;
        }

        $project = $this->composer->getPackage();
        $requires = $project->getRequires() + $project->getDevRequires();

        return $project->getName() === static::PACKAGE || array_key_exists(static::PACKAGE, $requires);
    }

    /**
     * Get the installer for the project's repository, or null outside a git repository.
     */
    protected function installer(): ?HookInstaller
    {
        try {
            return HookInstaller::for(Repository::fromDirectory($this->project()));
        } catch (RuntimeException) {
            return null;
        }
    }

    /**
     * Get the folder of the project being installed, which holds its composer.json.
     */
    protected function project(): string
    {
        return dirname((string) $this->composer->getConfig()->get('vendor-dir'));
    }
}
