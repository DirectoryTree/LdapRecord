<?php

namespace LdapRecord\Query\Filter;

class OrGroup extends BooleanGroup
{
    /**
     * {@inheritdoc}
     */
    public function getOperator(): string
    {
        return '|';
    }
}
