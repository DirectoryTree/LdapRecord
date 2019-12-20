<?php

namespace LdapRecord;

use LdapRecord\Auth\Guard;
use LdapRecord\Query\Cache;
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
    public function __construct($config = [], LdapInterface $ldap = null)
    {
        $this->setConfiguration($config);

        $this->setLdapConnection($ldap ?? new Ldap());
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
    public function setConfiguration($config = [])
    {
        $this->configuration = new DomainConfiguration($config);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function setLdapConnection(LdapInterface $ldap)
    {
        $this->ldap = $ldap;

        $this->setup();

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
        if (is_null($username) && is_null($password)) {
            $this->auth()->bindAsConfiguredUser();
        } else {
            $this->auth()->bind($username, $password);
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
     * Setup the LDAP connection.
     *
     * @return void
     */
    protected function setup()
    {
        $this->ldap->connect(
            $this->configuration->get('hosts'),
            $this->configuration->get('port')
        );

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
