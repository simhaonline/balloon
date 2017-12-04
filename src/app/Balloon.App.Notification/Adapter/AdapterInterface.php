<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Notification\Adapter;

use Balloon\Server\User;

interface AdapterInterface
{
    /**
     * Send notification.
     *
     * @param array receiver
     * @param User   $sender
     * @param string $subject
     * @param string $body
     * @param array  $context
     *
     * @return bool
     */
    public function notify(array $receiver, ?User $sender, string $subject, string $body, array $context = []): bool;
}