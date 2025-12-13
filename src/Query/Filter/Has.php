<?php

namespace LdapRecord\Query\Filter;

class Has implements ConditionFilter
{
    /**
     * Create a new has (presence) filter.
     */
    public function __construct(
        protected string $attribute
    ) {}

    /**
     * Get the filter's attribute.
     */
    public function getAttribute(): string
    {
        return $this->attribute;
    }

    /**
     * Get the filter's operator.
     */
    public function getOperator(): string
    {
        return '=';
    }

    /**
     * Get the filter's value.
     */
    public function getValue(): ?string
    {
        return null;
    }

    /**
     * Get the raw filter string (without parentheses).
     */
    public function getRaw(): string
    {
        return "{$this->attribute}=*";
    }

    /**
     * Compile the filter to its LDAP string representation.
     */
    public function __toString(): string
    {
        return "({$this->getRaw()})";
    }
}
