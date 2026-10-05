<?php

namespace LdapRecord\Query\Filter;

class Not implements GroupFilter
{
    /**
     * Create a new NOT filter.
     */
    public function __construct(
        protected Filter $filter
    ) {}

    /**
     * Get the wrapped filter.
     */
    public function getFilter(): Filter
    {
        return $this->filter;
    }

    /**
     * {@inheritdoc}
     */
    public function getOperator(): string
    {
        return '!';
    }

    /**
     * {@inheritdoc}
     */
    public function getRaw(): string
    {
        return '!'.$this->filter;
    }

    /**
     * {@inheritdoc}
     */
    public function __toString(): string
    {
        return '('.$this->getRaw().')';
    }
}
