<?php

namespace LdapRecord\Query\Filter;

class AndGroup extends BooleanGroup
{
    /**
     * {@inheritdoc}
     */
    public function getOperator(): string
    {
        return '&';
    }
}
