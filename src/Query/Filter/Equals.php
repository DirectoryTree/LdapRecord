<?php

namespace LdapRecord\Query\Filter;

class Equals implements ConditionFilter
{
    /**
     * Create a new equals filter.
     */
    public function __construct(
        protected string $attribute,
        protected string $value
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
    public function getValue(): string
    {
        return $this->value;
    }

    /**
     * {@inheritdoc}
     */
    public function getRaw(): string
    {
        return "{$this->attribute}={$this->value}";
    }

    /**
     * {@inheritdoc}
     */
    public function __toString(): string
    {
        return "({$this->getRaw()})";
    }
}
