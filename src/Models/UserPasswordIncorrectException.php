<?php

namespace LdapRecord\Models;

use LdapRecord\LdapRecordException;

/**
 * Class UserPasswordIncorrectException.
 *
 * Thrown when a users password is being changed
 * and their current password given is incorrect.
 */
class UserPasswordIncorrectException extends LdapRecordException
{
    //
}
