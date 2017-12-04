<?php

declare(strict_types=1);

/**
 * Balloon
 *
 * @author      Raffael Sahli <sahli@gyselroth.net>
 * @copyright   Copryright (c) 2012-2017 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Notification\App;

use Balloon\App\AppInterface;
use Balloon\App\Notification\Api\v1\Notification as Api;
use Micro\Http\Router;
use Micro\Http\Router\Route;

class Http implements AppInterface
{
    /**
     * Constructor.
     *
     * @param LoggerInterace $logger
     * @param Router         $router
     * @param iterable       $config
     */
    public function __construct(Router $router)
    {
        $router
            ->prependRoute(new Route('/api/v1/user/notification', Api::class))
            ->prependRoute(new Route('/api/v1/user/notification/{id:#([0-9a-z]{24})#}', Api::class))
            ->prependRoute(new Route('/api/v1/user/{id:#([0-9a-z]{24})#}/notification', Api::class));
    }
}