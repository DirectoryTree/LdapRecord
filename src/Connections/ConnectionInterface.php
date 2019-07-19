<?php

namespace LdapRecord\Connections;

use LdapRecord\Auth\GuardInterface;
use LdapRecord\Configuration\DomainConfiguration;

interface ConnectionInterface
{
    /**
     * Constructor.
     *
     * @param array|DomainConfiguration $configuration
     */
    public function __construct($configuration);

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
     * Returns the current Guard instance.
     *
     * @return \LdapRecord\Auth\Guard
     */
    public function getGuard();

    /**
     * Returns a new default Guard instance.
     *
     * @param LdapInterface $connection
     * @param DomainConfiguration $configuration
     *
     * @return \LdapRecord\Auth\Guard
     */
    public function getDefaultGuard(LdapInterface $connection, DomainConfiguration $configuration);

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
     * Sets the current Guard instance.
     *
     * @param GuardInterface $guard
     *
     * @return $this
     */
    public function setGuard(GuardInterface $guard);

    /**
     * Returns a new Search factory instance.
     *
     * @return \LdapRecord\Query\Factory
     */
    public function search();

    /**
     * Returns a new Auth Guard instance.
     *
     * @return \LdapRecord\Auth\Guard
     */
    public function auth();

    /**
     * Connects and Binds to the Domain Controller.
     *
     * If no username or password is specified, then the
     * configured administrator credentials are used.
     *
     * @param string|null $username
     * @param string|null $password
     *
     * @return ConnectionInterface
     *@throws ConnectionException        If upgrading the connection to TLS fails
     *
     * @throws \LdapRecord\Auth\BindException If binding to the LDAP server fails.
     */
    public function connect($username = null, $password = null);
}
