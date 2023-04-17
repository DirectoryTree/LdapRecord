<?php

namespace LdapRecord\Support;

use Closure;

class Helpers
{
    /**
     * Return the default value of the given value.
     */
    public static function value(mixed $value, mixed ...$args)
    {
        return $value instanceof Closure ? $value(...$args) : $value;
    }
}
