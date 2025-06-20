<?php

namespace LdapRecord\Support;

use Closure;

class Value
{
    /**
     * Get the default value of the given value.
     */
    public static function get(mixed $value, mixed ...$args): mixed
    {
        return $value instanceof Closure ? $value(...$args) : $value;
    }
}
