<?php

namespace ClaudioDekker\LaravelGuardrails;

/**
 * @internal
 */
class Environment
{
    /**
     * The environment variables that CI services set.
     */
    public const CI_VARIABLES = ['CI', 'GITHUB_ACTIONS', 'GITLAB_CI', 'JENKINS_URL'];

    /**
     * Determine if the process runs on a CI service, which sets one of the variables to something.
     */
    public static function isCi(): bool
    {
        foreach (self::CI_VARIABLES as $variable) {
            if (! in_array(getenv($variable), [false, ''], true)) {
                return true;
            }
        }

        return false;
    }
}
