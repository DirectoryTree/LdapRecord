<?php

namespace LdapRecord\Query\Filter;

interface ConditionFilter extends Filter
{
    /**
     * Get the filter's attribute.
     */
    public function getAttribute(): string;

    /**
     * Get the filter's operator.
     */
    public function getOperator(): string;

    /**
     * Get the filter's value.
     */
    public function getValue(): ?string;

    /**
     * Get the raw filter string (without parentheses).
     */
    public function getRaw(): string;
}
