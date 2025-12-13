<?php

namespace LdapRecord\Query\Filter;

interface GroupFilter extends Filter
{
    /**
     * Get the group's operator.
     */
    public function getOperator(): string;

    /**
     * Get the raw filter string (without outer parentheses).
     */
    public function getRaw(): string;
}
