<?php

namespace LdapRecord\Testing;

use LdapRecord\Container;
use LdapRecord\Connection;
use LdapRecord\Models\Model;

class FakeConnection extends Connection
{
    /**
     * The currently bound LDAP user.
     *
     * @var Model
     */
    protected $user;

    /**
     * The underlying fake LDAP connection.
     *
     * @var FakeLdapConnection
     */
    protected $ldap;

    /**
     * Make a new fake LDAP connection instance.
     *
     * @param array $config
     *
     * @return static
     */
    public static function make(array $config = [])
    {
        return new static($config, new FakeLdapConnection());
    }

    /**
     * Set the user to authenticate as.
     *
     * @param Model $user
     *
     * @return $this
     */
    public function actingAs(Model $user)
    {
        $this->user = $user;

        $this->ldap->shouldAuthenticateWith($user->getDn());

        return $this;
    }

    /**
     * Create a new fake auth guard.
     *
     * @return FakeAuthGuard
     */
    public function auth()
    {
        $guard = new FakeAuthGuard($this->ldap, $this->configuration);

        $guard->setDispatcher(Container::getEventDispatcher());

        return $guard;
    }

    /**
     * {@inheritdoc}
     */
    public function isConnected()
    {
        return true;
    }
}
