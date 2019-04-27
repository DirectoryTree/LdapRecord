<?php

namespace LdapRecord\Models;

use LdapRecord\LdapRecordException;

/**
 * Class UserPasswordPolicyException
 *
 * Thrown when a users password is being changed but their new password
 * does not conform to the LDAP servers password policy.
 *
 * @package LdapRecord\Models
 */
class UserPasswordPolicyException extends LdapRecordException
{
    //
}
