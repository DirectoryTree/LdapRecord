<?php

namespace LdapRecord\Auth;

use Exception;
use LdapRecord\DetailedError;
use LdapRecord\LdapRecordException;

class BindException extends LdapRecordException
{
    /**
     * The detailed LDAP error.
     *
     * @var DetailedError
     */
    protected $detailedError;

    /**
     * Create a new Bind Exception with a detailed connection error.
     *
     * @param DetailedError $error
     * @param Exception     $e
     *
     * @return BindException
     */
    public static function withDetailedError(DetailedError $error, Exception $e)
    {
        return (new static($e->getMessage(), $e->getCode(), $e))->setDetailedError($error);
    }

    /**
     * Sets the detailed error.
     *
     * @param DetailedError|null $error
     *
     * @return $this
     */
    public function setDetailedError(DetailedError $error = null)
    {
        $this->detailedError = $error;

        return $this;
    }

    /**
     * Returns the detailed error.
     *
     * @return DetailedError|null
     */
    public function getDetailedError()
    {
        return $this->detailedError;
    }
}
