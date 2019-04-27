<?php

namespace LdapRecord\Models;

use LdapRecord\LdapRecordException;

/**
 * Class UserPasswordIncorrectException
 *
 * Thrown when a users password is being changed
 * and their current password given is incorrect.
 *
 * @package LdapRecord\Models
 */
class UserPasswordIncorrectException extends LdapRecordException
{
    //
}
