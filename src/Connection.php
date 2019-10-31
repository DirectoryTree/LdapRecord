<?php

namespace LdapRecord;

use LdapRecord\Auth\Guard;
use LdapRecord\Query\Cache;
use InvalidArgumentException;
use LdapRecord\Query\Builder;
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
        if ($this->ldap->isBound()) {
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
    public function auth()
    {
        $guard = new Guard($this->ldap, $this->configuration);

        $guard->setDispatcher(Container::getEventDispatcher());

        return $guard;
    }

    /**
     * {@inheritdoc}
     */
    public function query()
    {
        return (new Builder($this->ldap))->in($this->configuration->get('base_dn'));
    }

    /**
     * {@inheritdoc}
     */
    public function connect($username = null, $password = null)
    {
        $guard = $this->auth();

        if (is_null($username) && is_null($password)) {
            // If both the username and password are null, we'll connect to the server
            // using the configured administrator username and password.
            $guard->bindAsConfiguredUser();
        } else {
            // Bind to the server with the specified username and password otherwise.
            $guard->bind($username, $password);
        }

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function isConnected()
    {
        return $this->ldap->isBound();
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
