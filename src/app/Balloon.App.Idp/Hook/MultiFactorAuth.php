<?php

declare(strict_types=1);

/**
 * balloon
 *
 * @copyright   Copryright (c) 2012-2019 gyselroth GmbH (https://gyselroth.com)
 * @license     GPL-3.0 https://opensource.org/licenses/GPL-3.0
 */

namespace Balloon\App\Idp\Hook;

use Balloon\App\Idp\Exception;
use Balloon\Hook\AbstractHook;
use Balloon\Server\RoleInterface;
use Balloon\Server\User;
use Dolondro\GoogleAuthenticator\SecretFactory;
use Micro\Auth\Adapter\Basic\BasicInterface;
use Micro\Auth\Identity;
use Psr\Log\LoggerInterface;

class MultiFactorAuth extends AbstractHook
{
    /**
     * Issuer.
     */
    public const ISSUER = 'balloon';

    /**
     * Logger.
     *
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * Secret factory.
     *
     * @var SecretFactory
     */
    protected $secret_factory;

    /**
     * Secret.
     *
     * @var Secret
     */
    protected $secret;

    /**
     * Constructor.
     */
    public function __construct(LoggerInterface $logger, SecretFactory $secret_factory)
    {
        $this->logger = $logger;
        $this->secret_factory = $secret_factory;
    }

    /**
     * {@inheritdoc}
     */
    public function preUpdateUser(User $user, array &$attributes = []): void
    {
        $existing = $user->getAttributes();
        if ($attributes['multi_factor_auth'] === true) {
            $secret = $this->secret_factory->create(self::ISSUER, $user->getUsername());
            $attributes['google_auth_secret'] = $secret->getSecretKey();
            $this->secret = $secret;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function preDecorateRole(RoleInterface $role, array &$attributes = []): void
    {
        $mfa = $role->getAttributes()['multi_factor_auth'];
        $attributes['multi_factor_auth'] = $mfa;

        if ($this->secret !== null) {
            $attributes['multi_factor_uri'] = $this->secret->getUri();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function preServerIdentity(Identity $identity, ?User &$user): void
    {
        if (null === $user) {
            return;
        }

        if ($identity->getAdapter() instanceof BasicInterface && $user->getAttributes()['multi_factor_auth'] === true) {
            throw new Exception\MultiFactorAuthenticationRequired('multi-factor authentication required');
        }

        $this->logger->debug('multi-factor authenticatoin is not required for user ['.$user->getId().']', [
            'category' => get_class($this),
        ]);
    }
}
