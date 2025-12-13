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
     * {@inheritdoc}
     */
    public function getAttribute(): string
    {
        return $this->attribute;
    }

    /**
     * {@inheritdoc}
     */
    public function getOperator(): string
    {
        return '=';
    }

    /**
     * {@inheritdoc}
     */
    public function getValue(): ?string
    {
        return null;
    }

    /**
     * {@inheritdoc}
     */
    public function getRaw(): string
    {
        return "{$this->attribute}=*";
    }

    /**
     * {@inheritdoc}
     */
    public function __toString(): string
    {
        return "({$this->getRaw()})";
    }
}
