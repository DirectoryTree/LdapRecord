<?php

namespace LdapRecord;

use Psr\SimpleCache\CacheInterface;
use LdapRecord\Configuration\DomainConfiguration;

interface ConnectionInterface
{
    /**
     * Constructor.
     *
     * @param array              $config
     * @param LdapInterface|null $ldap
     */
    public function __construct($config, LdapInterface $ldap = null);

    /**
     * Get the LDAP connection instance.
     *
     * @return LdapInterface
     */
    public function getLdapConnection();

    /**
     * Get the LDAP configuration instance.
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
     * Set the LDAP connection.
     *
     * @param LdapInterface $connection
     *
     * @return $this
     */
    public function setLdapConnection(LdapInterface $connection);

    /**
     * Set the connection configuration.
     *
     * @param array $config
     *
     * @throws \LdapRecord\Configuration\ConfigurationException
     */
    public function setConfiguration($config = []);

    /**
     * Get a new auth guard instance.
     *
     * @return \LdapRecord\Auth\Guard
     */
    public function auth();

    /**
     * Get a new query builder for the connection.
     *
     * @return \LdapRecord\Query\Builder
     */
    public function query();

    /**
     * Connect to the Domain Controller.
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

    /**
     * Determine if the LDAP connection is bound.
     *
     * @return bool
     */
    public function isConnected();
}
