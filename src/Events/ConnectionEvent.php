<?php

namespace LdapRecord\Events;

use LdapRecord\Connection;

abstract class ConnectionEvent
{
    /**
     * The LDAP connection.
     *
     * @var Connection
     */
    public $connection;

    /**
     * Constructor.
     *
     * @param Connection $connection
     */
    public function __construct(Connection $connection)
    {
        $this->connection = $connection;
    }
}
