<?php

namespace LdapRecord;

use LdapRecord\Auth\Guard;
use LdapRecord\Query\Cache;
use InvalidArgumentException;
use LdapRecord\Auth\GuardInterface;
use Psr\SimpleCache\CacheInterface;
use LdapRecord\Configuration\DomainConfiguration;

class Connection implements ConnectionInterface
{
    /**
     * @var LdapInterface
     */
    protected $ldap;

    /**
     * @var DomainConfiguration
     */
    protected $configuration;

    /**
     * @var GuardInterface
     */
    protected $guard;

    /**
     * @var Cache|null
     */
    protected $cache;

    /**
     * {@inheritdoc}
     */
    public function __construct($configuration = [], LdapInterface $ldap = null)
    {
        $this->setConfiguration($configuration)
            ->setLdapConnection($ldap ?? new Ldap());
    }

    /**
     * Close the LDAP connection (if bound) upon destruction.
     *
     * @return void
     */
    public function __destruct()
    {
        if (
            $this->ldap instanceof LdapInterface &&
            $this->ldap->isBound()
        ) {
            $this->ldap->close();
        }
    }

    /**
     * {@inheritdoc}
     */
    public function setConfiguration($configuration = [])
    {
        if (is_array($configuration)) {
            $configuration = new DomainConfiguration($configuration);
        }

        if ($configuration instanceof DomainConfiguration) {
            $this->configuration = $configuration;

            return $this;
        }

        throw new InvalidArgumentException(
            sprintf('Configuration must be array or instance of %s', DomainConfiguration::class)
        );
    }

    /**
     * {@inheritdoc}
     */
    public function setLdapConnection(LdapInterface $ldap = null)
    {
        // We will create a standard connection if one isn't given.
        $this->ldap = $ldap ?: new Ldap();

        // Prepare the connection.
        $this->prepareConnection();

        // Instantiate the LDAP connection.
        $this->ldap->connect(
            $this->configuration->get('hosts'),
            $this->configuration->get('port')
        );

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setGuard(GuardInterface $guard)
    {
        $this->guard = $guard;

        return $this;
    }

    /**
     * Sets the cache store.
     *
     * @param CacheInterface $store
     *
     * @return $this
     */
    public function setCache(CacheInterface $store)
    {
        $this->cache = new Cache($store);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getConfiguration()
    {
        return $this->configuration;
    }

    /**
     * {@inheritdoc}
     */
    public function getLdapConnection()
    {
        return $this->ldap;
    }

    /**
     * {@inheritdoc}
     */
    public function getGuard()
    {
        if (!$this->guard instanceof GuardInterface) {
            $this->setGuard($this->getDefaultGuard($this->ldap, $this->configuration));
        }

        return $this->guard;
    }

    /**
     * {@inheritdoc}
     */
    public function getDefaultGuard(LdapInterface $connection, DomainConfiguration $configuration)
    {
        $guard = new Guard($connection, $configuration);

        $guard->setDispatcher(Container::getEventDispatcher());

        return $guard;
    }

    /**
     * {@inheritdoc}
     */
    public function auth()
    {
        return $this->getGuard();
    }

    /**
     * {@inheritdoc}
     */
    public function connect($username = null, $password = null)
    {
        // Get the default guard instance.
        $guard = $this->getGuard();

        if (is_null($username) && is_null($password)) {
            // If both the username and password are null, we'll connect to the server
            // using the configured administrator username and password.
            $guard->bindAsAdministrator();
        } else {
            // Bind to the server with the specified username and password otherwise.
            $guard->bind($username, $password);
        }

        return $this;
    }

    /**
     * Prepares the connection by setting configured parameters.
     *
     * @throws \LdapRecord\Configuration\ConfigurationException When configuration options requested do not exist
     *
     * @return void
     */
    protected function prepareConnection()
    {
        if ($this->configuration->get('use_ssl')) {
            $this->ldap->ssl();
        } elseif ($this->configuration->get('use_tls')) {
            $this->ldap->tls();
        }

        $options = array_replace(
            $this->configuration->get('options'),
            [
                LDAP_OPT_PROTOCOL_VERSION => $this->configuration->get('version'),
                LDAP_OPT_NETWORK_TIMEOUT  => $this->configuration->get('timeout'),
                LDAP_OPT_REFERRALS        => $this->configuration->get('follow_referrals'),
            ]
        );

        $this->ldap->setOptions($options);
    }
}
