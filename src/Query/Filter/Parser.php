<?php

namespace LdapRecord\Query\Filter;

use LdapRecord\Support\Arr;
use LdapRecord\Support\Str;

class Parser
{
    /**
     * Parse an LDAP filter into filter objects.
     *
     * @return Filter[]
     *
     * @throws ParserException
     */
    public static function parse(string $string): array
    {
        [$open, $close] = static::countParenthesis($string);

        if ($open !== $close) {
            $errors = [-1 => '"("', 1 => '")"'];

            throw new ParserException(
                sprintf('Unclosed filter group. Missing %s parenthesis', $errors[$open <=> $close])
            );
        }

        return static::buildFilters(
            array_map('trim', static::match($string))
        );
    }

    /**
     * Perform a match for all filters in the string.
     */
    protected static function match(string $string): array
    {
        preg_match_all("/\((((?>[^()]+)|(?R))*)\)/", trim($string), $matches);

        return $matches[1] ?? [];
    }

    /**
     * Assemble the parsed filters into a single string.
     *
     * @param  Filter|Filter[]  $filters
     */
    public static function assemble(Filter|array $filters = []): string
    {
        return implode(array_map(
            fn (Filter $filter) => (string) $filter,
            Arr::wrap($filters)
        ));
    }

    /**
     * Build an array of filter objects from the given filter strings.
     *
     * @param  string[]  $filters
     * @return Filter[]
     *
     * @throws ParserException
     */
    protected static function buildFilters(array $filters = []): array
    {
        return array_map(function ($filter) {
            if (static::isWrapped($filter)) {
                $filter = static::unwrap($filter);
            }

            if (static::isGroup($filter) && ! Str::endsWith($filter, ')')) {
                throw new ParserException(sprintf('Unclosed filter group [%s]', Str::afterLast($filter, ')')));
            }

            return static::isGroup($filter)
                ? static::buildGroup($filter)
                : static::buildCondition($filter);
        }, $filters);
    }

    /**
     * Build a group filter from the given filter string.
     */
    protected static function buildGroup(string $filter): Filter
    {
        $operator = substr($filter, 0, 1);

        $children = static::parse($filter);

        return match ($operator) {
            '&' => new AndGroup(...$children),
            '|' => new OrGroup(...$children),
            '!' => new Not(Arr::first($children)),
        };
    }

    /**
     * Build a condition filter from the given filter string.
     *
     * @throws ParserException
     */
    protected static function buildCondition(string $filter): Filter
    {
        // Order matters here. Check multi-char operators first.
        $operators = ['>=', '<=', '~=', '='];

        foreach ($operators as $operator) {
            if (Str::contains($filter, $operator)) {
                [$attribute, $value] = explode($operator, $filter, 2);

                return static::createConditionFilter($operator, $attribute, $value);
            }
        }

        throw new ParserException("Invalid query condition. No operator found in [$filter]");
    }

    /**
     * Create the appropriate filter instance based on operator and value pattern.
     */
    protected static function createConditionFilter(string $operator, string $attribute, string $value): Filter
    {
        return match ($operator) {
            '>=' => new GreaterThanOrEquals($attribute, $value),
            '<=' => new LessThanOrEquals($attribute, $value),
            '~=' => new ApproximatelyEquals($attribute, $value),
            '=' => static::createEqualsFilter($attribute, $value),
        };
    }

    /**
     * Create the appropriate equals-based filter based on value pattern.
     */
    protected static function createEqualsFilter(string $attribute, string $value): Filter
    {
        // Has: (attr=*)
        if ($value === '*') {
            return new Has($attribute);
        }

        // Contains: (attr=*value*)
        if (Str::startsWith($value, '*') && Str::endsWith($value, '*')) {
            return new Contains($attribute, substr($value, 1, -1));
        }

        // EndsWith: (attr=*value)
        if (Str::startsWith($value, '*')) {
            return new EndsWith($attribute, substr($value, 1));
        }

        // StartsWith: (attr=value*)
        if (Str::endsWith($value, '*')) {
            return new StartsWith($attribute, substr($value, 0, -1));
        }

        // Equals: (attr=value)
        return new Equals($attribute, $value);
    }

    /**
     * Count the open and close parenthesis of the sting.
     */
    protected static function countParenthesis(string $string): array
    {
        return [Str::substrCount($string, '('), Str::substrCount($string, ')')];
    }

    /**
     * Recursively unwrap the value from its parentheses.
     */
    protected static function unwrap(string $value): string
    {
        $filter = Arr::first(static::parse($value));

        return $filter?->getRaw() ?? $value;
    }

    /**
     * Determine if the filter is wrapped.
     */
    protected static function isWrapped(string $filter): bool
    {
        return Str::startsWith($filter, '(') && Str::endsWith($filter, ')');
    }

    /**
     * Determine if the filter is a group.
     */
    protected static function isGroup(string $filter): bool
    {
        return Str::startsWith($filter, ['&', '|', '!']);
    }
}
