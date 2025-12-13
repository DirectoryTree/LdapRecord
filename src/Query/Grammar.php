<?php

namespace LdapRecord\Query;

use LdapRecord\Query\Filter\ApproximatelyEquals;
use LdapRecord\Query\Filter\Contains;
use LdapRecord\Query\Filter\EndsWith;
use LdapRecord\Query\Filter\Equals;
use LdapRecord\Query\Filter\Filter;
use LdapRecord\Query\Filter\GreaterThanOrEquals;
use LdapRecord\Query\Filter\Has;
use LdapRecord\Query\Filter\LessThanOrEquals;
use LdapRecord\Query\Filter\Not;
use LdapRecord\Query\Filter\StartsWith;
use UnexpectedValueException;

class Grammar
{
    /**
     * The query operators and their filter classes.
     */
    public array $operators = [
        '*' => Has::class,
        '!*' => [Not::class, Has::class],
        '=' => Equals::class,
        '!' => [Not::class, Equals::class],
        '!=' => [Not::class, Equals::class],
        '>=' => GreaterThanOrEquals::class,
        '<=' => LessThanOrEquals::class,
        '~=' => ApproximatelyEquals::class,
        'starts_with' => StartsWith::class,
        'not_starts_with' => [Not::class, StartsWith::class],
        'ends_with' => EndsWith::class,
        'not_ends_with' => [Not::class, EndsWith::class],
        'contains' => Contains::class,
        'not_contains' => [Not::class, Contains::class],
    ];

    /**
     * Get the available operators.
     */
    public function operators(): array
    {
        return array_keys($this->operators);
    }

    /**
     * Compiles the builder instance into an LDAP query string.
     */
    public function compile(Builder $query): string
    {
        return $query->getFilter() ? (string) $query->getFilter() : '';
    }

    /**
     * Make a filter instance for the operator.
     *
     * @throws UnexpectedValueException
     */
    public function makeFilter(string $operator, string $attribute, ?string $value = null): Filter
    {
        if (! $this->operatorExists($operator)) {
            throw new UnexpectedValueException("Invalid LDAP filter operator ['$operator']");
        }

        $classes = $this->operators[$operator];

        // Handle negated operators (e.g., [Not::class, Equals::class])
        if (is_array($classes)) {
            [$wrapper, $filterClass] = $classes;

            return new $wrapper($this->createFilter($filterClass, $attribute, $value));
        }

        return $this->createFilter($classes, $attribute, $value);
    }

    /**
     * Create a filter instance.
     */
    protected function createFilter(string $filterClass, string $attribute, ?string $value = null): Filter
    {
        // Some operators like Has don't require a value
        if ($filterClass === Has::class) {
            return new $filterClass($attribute);
        }

        return new $filterClass($attribute, $value);
    }

    /**
     * Determine if the operator exists.
     */
    protected function operatorExists(string $operator): bool
    {
        return array_key_exists($operator, $this->operators);
    }
}
