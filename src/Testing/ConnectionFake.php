<?php

namespace LdapRecord\Testing;

use LdapRecord\Auth\Guard;
use LdapRecord\Connection;
use LdapRecord\LdapInterface;
use LdapRecord\Models\Model;

class ConnectionFake extends Connection
{
    /**
     * The underlying fake LDAP connection.
     *
     * @var LdapFake
     */
    protected LdapInterface $ldap;

    /**
     * Whether the fake is connected.
     */
    protected bool $connected = false;

    /**
     * Whether the fake is a replica sharing another connection's fake LDAP connection.
     */
    protected bool $replicated = false;

    /**
     * Make a new fake LDAP connection instance.
     */
    public static function make(array $config = [], string $ldap = LdapFake::class): static
    {
        $connection = new static($config, new $ldap);

        $connection->configure();

        return $connection;
    }

    /**
     * Clone the connection, reusing the same fake LDAP connection.
     *
     * Sharing the underlying fake allows operations performed on an isolated
     * connection (e.g. self-service password changes) to be asserted through
     * the original fake's expectations.
     */
    public function replicate(): static
    {
        $replica = new static($this->configuration, $this->ldap);

        $replica->replicated = true;

        return $replica;
    }

    /**
     * {@inheritdoc}
     *
     * Disconnecting a replica must not reset the state of
     * the fake LDAP connection it shares with its parent.
     */
    public function disconnect(): void
    {
        if (! $this->replicated) {
            parent::disconnect();
        }
    }

    /**
     * Set the user to authenticate as.
     */
    public function actingAs(Model|string $user): static
    {
        $this->ldap->shouldAllowBindWith(
            $user instanceof Model ? $user->getDn() : $user
        );

        return $this;
    }

    /**
     * Set the connection to bypass bind attempts as the configured user.
     */
    public function shouldBeConnected(): static
    {
        $this->connected = true;

        $this->authGuardResolver = function () {
            return new AuthGuardFake($this->ldap, $this->configuration);
        };

        return $this;
    }

    /**
     * Set the connection to attempt binding as the configured user.
     */
    public function shouldNotBeConnected(): static
    {
        $this->connected = false;

        $this->authGuardResolver = function () {
            return new Guard($this->ldap, $this->configuration);
        };

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function isConnected(): bool
    {
        return $this->connected ?: parent::isConnected();
    }

    /**
     * Perform tear down tasks on the fake.
     *
     * @throws LdapExpectationException
     */
    public function tearDown(): void
    {
        $this->ldap->assertMinimumExpectationCounts();
    }
}
