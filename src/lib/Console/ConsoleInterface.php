<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Console;

interface ConsoleInterface
{
    /**
     * Start.
     *
     * @return bool
     */
    public function start(): bool;

    /**
     * Set options.
     *
     * @return ConsoleInterface
     */
    public function setOptions(): self;

    /**
     * Get description.
     *
     * @return string
     */
    public function getDescription(): string;
}
