<?php

namespace LdapRecord\Query\Filter;

use LdapRecord\Support\Str;

class ConditionNode extends Node
{
    /**
     * The condition's attribute.
     *
     * @var string
     */
    protected $attribute;

    /**
     * The condition's operator.
     *
     * @var string
     */
    protected $operator;

    /**
     * The condition's value.
     *
     * @var string
     */
    protected $value;

    /**
     * The available condition operators.
     *
     * @var array
     */
    protected $operators = ['>=', '<=', '~=', '='];

    /**
     * Constructor.
     *
     * @param string $filter
     */
    public function __construct($filter)
    {
        $this->raw = $filter;

        $components = Str::whenContains(
            $filter,
            $this->operators,
            fn ($operator, $filter) => explode($this->operator = $operator, $filter),
            fn ($filter) => throw new ParserException("Invalid query condition. No operator found in [$filter]")
        );

        if (count($components) !== 2) {
            throw new ParserException("Invalid query filter [$filter]");
        }

        [$this->attribute, $this->value] = $components;
    }

    /**
     * Get the condition's attribute.
     *
     * @return string
     */
    public function getAttribute()
    {
        return $this->attribute;
    }

    /**
     * Get the condition's operator.
     *
     * @return string
     */
    public function getOperator()
    {
        return $this->operator;
    }

    /**
     * Get the condition's value.
     *
     * @return string
     */
    public function getValue()
    {
        return $this->value;
    }
}
