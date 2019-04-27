<?php

namespace LdapRecord\Models;

/**
 * Class Contact
 *
 * Represents an LDAP contact.
 *
 * @package LdapRecord\Models
 */
class Contact extends Entry
{
    use Concerns\HasMemberOf,
        Concerns\HasUserProperties;
}
