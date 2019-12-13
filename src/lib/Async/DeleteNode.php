<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\Async;

use Psr\Log\LoggerInterface;
use TaskScheduler\AbstractJob;
use Balloon\User\Factory as UserFactory;
use Balloon\Node\Factory as NodeFactory;

class DeleteNode extends AbstractJob
{
    /**
     * Constructor.
     */
    public function __construct(UserFactory $user_factory, NodeFactory $node_factory, LoggerInterface $logger)
    {
        $this->user_factory = $user_factory;
        $this->node_factory = $node_factory;
    }

    /**
     * {@inheritdoc}
     */
    public function start(): bool
    {
        $force = isset($this->data['force']) ? (bool)$this->data['force'] : false;
        $user = $this->user_factory->getOne($this->data['owner']);
        $this->node_factory->deleteOne($user, $this->data['node'], $force);

        return true;
    }
}
