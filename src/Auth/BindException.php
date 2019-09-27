<?php

namespace LdapRecord\Auth;

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
