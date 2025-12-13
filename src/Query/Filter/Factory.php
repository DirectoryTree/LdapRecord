<?php

namespace LdapRecord\Query\Filter;

use UnexpectedValueException;

class Factory
{
    /**
     * The query operators and their filter classes.
     */
    protected static array $operators = [
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
    public static function operators(): array
    {
        return array_keys(static::$operators);
    }

    /**
     * Make a filter instance for the operator.
     *
     * @throws UnexpectedValueException
     */
    public static function make(string $operator, string $attribute, ?string $value = null): Filter
    {
        if (! array_key_exists($operator, static::$operators)) {
            throw new UnexpectedValueException("Invalid LDAP filter operator ['$operator']");
        }

        if (is_array($classes = static::$operators[$operator])) {
            [$wrapper, $filter] = $classes;

            return new $wrapper(static::create($filter, $attribute, $value));
        }

        return static::create($classes, $attribute, $value);
    }

    /**
     * Create a filter instance.
     */
    protected static function create(string $filter, string $attribute, ?string $value = null): Filter
    {
        return $filter === Has::class
            ? new $filter($attribute)
            : new $filter($attribute, $value);
    }
}
