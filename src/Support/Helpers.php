<?php

namespace LdapRecord\Support;

use Closure;

class Helpers
{
    /**
     * Return the default value of the given value.
     */
    public static function value($value, ...$args)
    {
        return $value instanceof Closure ? $value(...$args) : $value;
    }
}
