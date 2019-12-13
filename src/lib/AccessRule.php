<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon;

use Psr\Http\Message\ServerRequestInterface;
use Balloon\AccessRule\AccessRuleInterface;
use Balloon\Resource\AbstractResource;
use Balloon\Resource\AttributeResolver;

class AccessRule extends AbstractResource implements AccessRuleInterface
{
    /**
     * Kind.
     */
    public const KIND = 'AccessRule';

    /**
     * Data object.
     */
    public function __construct(array $resource)
    {
        $this->resource = $resource;
    }

    public function getRules(): array
    {
        return $this->resource['rules'] ?? [];
    }
}