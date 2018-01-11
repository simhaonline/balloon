<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2018 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Console;

use Balloon\Hook;
use GetOpt\GetOpt;
use GetOpt\Option;
use Psr\Log\LoggerInterface;
use TaskScheduler\Async;

class Jobs implements ConsoleInterface
{
    /**
     * Logger.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Getopt.
     *
     * @var GetOpt
     */
    protected $getopt;

    /**
     * Async.
     *
     * @var Async
     */
    protected $async;

    /**
     * Hook.
     *
     * @var Hook
     */
    protected $hook;

    /**
     * Constructor.
     *
     * @param App             $app
     * @param Async           $async
     * @param LoggerInterface $logger
     * @param GetOpt          $getopt
     */
    public function __construct(Hook $hook, Async $async, LoggerInterface $logger, GetOpt $getopt)
    {
        $this->async = $async;
        $this->hook = $hook;
        $this->logger = $logger;
        $this->getopt = $getopt;
        $this->async = $async;
    }

    /**
     * Get description.
     *
     * @return string
     */
    public function getDescription(): string
    {
        return 'Handles asynchronous job queue';
    }

    /**
     * Set options.
     *
     * @return ConsoleInterface
     */
    public function setOptions(): ConsoleInterface
    {
        $this->getopt->addOptions([
            Option::create('d', 'daemon'),
        ]);

        return $this;
    }

    /**
     * Start.
     *
     * @return bool
     */
    public function start(): bool
    {
        if (null !== $this->getopt->getOption('daemon')) {
            $this->fireupDaemon();
        } else {
            $this->hook->run('preExecuteAsyncJobs');
            $this->async->startOnce();
            $this->hook->run('postExecuteAsyncJobs');
        }

        return true;
    }

    /**
     * Fire up daemon.
     *
     * @return bool
     */
    protected function fireupDaemon(): bool
    {
        $this->logger->info('daemon execution requested, fire up daemon', [
            'category' => get_class($this),
        ]);

        $this->hook->run('preExecuteAsyncJobs');
        $this->async->startDaemon();

        return true;
    }
}