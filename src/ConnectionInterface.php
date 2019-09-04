<?php

namespace LdapRecord;

use Psr\SimpleCache\CacheInterface;
use LdapRecord\Configuration\DomainConfiguration;

interface ConnectionInterface
{
    /**
     * Constructor.
     *
     * @param array|DomainConfiguration $configuration
     * @param LdapInterface|null        $ldap
     */
    public function __construct($configuration, LdapInterface $ldap = null);

    /**
     * Returns the current connection instance.
     *
     * @return LdapInterface
     */
    public function getLdapConnection();

    /**
     * Returns the current configuration instance.
     *
     * @return DomainConfiguration
     */
    public function getConfiguration();

    /**
     * Sets the cache store.
     *
     * @param CacheInterface $store
     *
     * @return $this
     */
    public function setCache(CacheInterface $store);

    /**
     * Sets the current connection.
     *
     * @param LdapInterface $connection
     *
     * @return $this
     */
    public function setLdapConnection(LdapInterface $connection = null);

    /**
     * Sets the current configuration.
     *
     * @param DomainConfiguration|array $configuration
     *
     * @throws \LdapRecord\Configuration\ConfigurationException
     */
    public function setConfiguration($configuration = []);

    /**
     * Returns a new auth guard instance.
     *
     * @return \LdapRecord\Auth\Guard
     */
    public function auth();

    /**
     * Returns a new query builder for the current connection.
     *
     * @return \LdapRecord\Query\Builder
     */
    public function query();

    /**
     * Connects and Binds to the Domain Controller.
     *
     * If no username or password is specified, then the
     * configured administrator credentials are used.
     *
     * @param string|null $username
     * @param string|null $password
     *
     * @throws ConnectionException            If upgrading the connection to TLS fails
     * @throws \LdapRecord\Auth\BindException If binding to the LDAP server fails.
     *
     * @return ConnectionInterface
     */
    public function connect($username = null, $password = null);
}
