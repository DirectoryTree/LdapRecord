<?php

namespace LdapRecord\Query\Filter;

use LdapRecord\Support\Str;

class Parser
{
    /**
     * Parse an LDAP filter.
     *
     * @param string $string
     *
     * @return GroupNode|ConditionNode[]
     */
    public static function parse($string)
    {
        preg_match_all("/\((((?>[^()]+)|(?R))*)\)/", trim($string), $matches);

        $extracted = $matches[1];

        $filter = reset($extracted);

        switch (true) {
            case static::isWrapped($filter):
                return static::parse($filter);
            case ! static::isGroup($filter):
                return static::buildConditions($extracted);
            case count($extracted) > 1:
                throw new ParserException(sprintf('Multiple root filters detected in [%s]', $string));
            case ! Str::endsWith($filter, ')'):
                throw new ParserException(sprintf('Unclosed filter group [%s]', Str::afterLast($filter, ')')));
            default:
                return new GroupNode($filter);
        }
    }

    /**
     * Build an array of conditions.
     *
     * @param array<string> $filters
     *
     * @return ConditionNode[]
     */
    protected static function buildConditions(array $filters = [])
    {
        return array_map(fn ($filter) => new ConditionNode($filter), $filters);
    }

    /**
     * Determine if the filter is wrapped.
     *
     * @param string $filter
     *
     * @return bool
     */
    protected static function isWrapped($filter)
    {
        return Str::startsWith($filter, '(');
    }

    /**
     * Determine if the filter is a group.
     *
     * @param string $filter
     *
     * @return bool
     */
    protected static function isGroup($filter)
    {
        return in_array(substr($filter, 0, 1), ['&', '|', '!']);
    }
}
