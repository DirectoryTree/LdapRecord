<?php

namespace LdapRecord\Query\Filter;

class ApproximatelyEquals implements Filter
{
    /**
     * Create a new approximately equals filter.
     */
    public function __construct(
        protected string $attribute,
        protected string $value
    ) {}

    /**
     * Compile the filter to its LDAP string representation.
     */
    public function __toString(): string
    {
        return "({$this->attribute}~={$this->value})";
    }
}
