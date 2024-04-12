<?php

namespace LdapRecord\Auth\Events;

use Exception;
use LdapRecord\LdapInterface;

class Failed extends Event
{
    /**
     * The exception that was thrown during the bind attempt.
     */
    protected Exception $exception;

    /**
     * Constructor.
     */
    public function __construct(LdapInterface $connection, ?string $username, ?string $password, Exception $exception)
    {
        parent::__construct($connection, $username, $password);

        $this->exception = $exception;
    }

    /**
     * Get the exception that was thrown.
     */
    public function getException(): Exception
    {
        return $this->exception;
    }
}
