<?php

namespace LdapRecord\Query\Filter;

class LessThanOrEquals implements ConditionFilter
{
    /**
     * Create a new less than or equals filter.
     */
    public function __construct(
        protected string $attribute,
        protected string $value
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
        return '<=';
    }

    /**
     * Get the filter's value.
     */
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * Get the raw filter string (without parentheses).
     */
    public function getRaw(): string
    {
        return "{$this->attribute}<={$this->value}";
    }

    /**
     * Compile the filter to its LDAP string representation.
     */
    public function __toString(): string
    {
        return "({$this->getRaw()})";
    }
}
