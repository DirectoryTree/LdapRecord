<?php

namespace LdapRecord\Query\Filter;

use Stringable;

interface Filter extends Stringable
{
    /**
     * Compile the filter to its LDAP string representation.
     */
    public function __toString(): string;
}
