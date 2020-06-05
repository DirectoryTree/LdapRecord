<?php

namespace LdapRecord\Exceptions;

use LdapRecord\DetectsErrors;
use LdapRecord\LdapRecordException;

class ConstraintViolationException extends LdapRecordException
{
    use DetectsErrors;

    /**
     * Determine if the exception was generated due to the password policy.
     *
     * @return bool
     */
    public function causedByPasswordPolicy()
    {
        return $this->errorContainsMessage($this->detailedError->getDiagnosticMessage(), '0000052D');
    }

    /**
     * Determine if the exception was generated due to an incorrect password.
     *
     * @return bool
     */
    public function causedByIncorrectPassword()
    {
        return $this->errorContainsMessage($this->detailedError->getDiagnosticMessage(), '00000056');
    }
}
