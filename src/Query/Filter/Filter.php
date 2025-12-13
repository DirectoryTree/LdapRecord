<?php

namespace LdapRecord\Query\Filter;

interface Filter extends \Stringable
{
    /**
     * Compile the filter to its LDAP string representation.
     */
    public function __toString(): string;
}
