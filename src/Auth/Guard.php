<?php

namespace LdapRecord\Auth;

use Exception;
use LdapRecord\Configuration\DomainConfiguration;
use LdapRecord\Events\DispatcherInterface;
use LdapRecord\LdapInterface;

class Guard
{
    /**
     * The connection to bind to.
     */
    protected LdapInterface $connection;

    /**
     * The domain configuration to utilize.
     */
    protected DomainConfiguration $configuration;

    /**
     * The event dispatcher.
     */
    protected DispatcherInterface $events;

    /**
     * Constructor.
     */
    public function __construct(LdapInterface $connection, DomainConfiguration $configuration)
    {
        $this->connection = $connection;
        $this->configuration = $configuration;
    }

    /**
     * Attempt binding a user to the LDAP server.
     *
     * @throws UsernameRequiredException
     * @throws PasswordRequiredException
     */
    public function attempt(string $username, string $password, bool $stayBound = false): bool
    {
        switch (true) {
            case empty($username):
                throw new UsernameRequiredException('A username must be specified.');
            case empty($password):
                throw new PasswordRequiredException('A password must be specified.');
        }

        $this->fireAuthEvent('attempting', $username, $password);

        try {
            $this->bind($username, $password);

            $authenticated = true;

            $this->fireAuthEvent('passed', $username, $password);
        } catch (BindException) {
            $authenticated = false;
        }

        if (! $stayBound) {
            $this->bindAsConfiguredUser();
        }

        return $authenticated;
    }

    /**
     * Attempt binding a user to the LDAP server. Supports anonymous binding.
     *
     * @throws BindException
     * @throws \LdapRecord\ConnectionException
     */
    public function bind(string $username = null, string $password = null): void
    {
        $this->fireAuthEvent('binding', $username, $password);

        // Prior to binding, we will upgrade our connectivity to TLS on our current
        // connection and ensure we are not already bound before upgrading.
        // This is to prevent subsequent upgrading on several binds.
        if ($this->connection->isUsingTLS() && ! $this->connection->isBound()) {
            $this->connection->startTLS();
        }

        try {
            if ($this->connection->bind($username, $password)->failed()) {
                throw new Exception($this->connection->getLastError(), $this->connection->errNo());
            }

            $this->fireAuthEvent('bound', $username, $password);
        } catch (Exception $e) {
            $this->fireAuthEvent('failed', $username, $password);

            throw BindException::withDetailedError($e, $this->connection->getDetailedError());
        }
    }

    /**
     * Bind to the LDAP server using the configured username and password.
     *
     * @throws BindException
     * @throws \LdapRecord\ConnectionException
     * @throws \LdapRecord\Configuration\ConfigurationException
     */
    public function bindAsConfiguredUser(): void
    {
        $this->bind(
            $this->configuration->get('username'),
            $this->configuration->get('password')
        );
    }

    /**
     * Get the event dispatcher instance.
     */
    public function getDispatcher(): DispatcherInterface
    {
        return $this->events;
    }

    /**
     * Set the event dispatcher instance.
     */
    public function setDispatcher(DispatcherInterface $dispatcher): void
    {
        $this->events = $dispatcher;
    }

    /**
     * Fire an authentication event.
     */
    protected function fireAuthEvent(string $name, string $username = null, string $password = null): void
    {
        if (isset($this->events)) {
            $event = implode('\\', [Events::class, ucfirst($name)]);

            $this->events->fire(new $event($this->connection, $username, $password));
        }
    }
}
