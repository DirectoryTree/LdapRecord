<?php

namespace LdapRecord\Query\Filter;

use LdapRecord\Support\Arr;
use LdapRecord\Support\Str;

class Parser
{
    /**
     * Parse an LDAP filter into nodes.
     *
     * @param string $string
     *
     * @return GroupNode|ConditionNode[]
     */
    public static function parse($string)
    {
        [$open, $close] = static::countParenthesis($string);

        if ($open !== $close) {
            $errors = [-1 => '"("', 1 => '")"'];

            throw new ParserException(
                sprintf('Unclosed filter group. Missing %s parenthesis', $errors[$open <=> $close])
            );
        }

        $matches = static::match($string);

        $extracted = $matches[1];

        $filter = reset($extracted);

        switch (true) {
            case static::isWrapped($filter):
                return static::parse($filter);
            case ! static::isGroup($filter):
                return static::buildNodes($extracted);
            case count($extracted) > 1:
                throw new ParserException(sprintf('Multiple root filters detected in [%s]', $string));
            case ! Str::endsWith($filter, ')'):
                throw new ParserException(sprintf('Unclosed filter group [%s]', Str::afterLast($filter, ')')));
            default:
                return new GroupNode($filter);
        }
    }

    /**
     * Perform a match for all filters in the string.
     *
     * @param string $string
     *
     * @return array
     */
    protected static function match($string)
    {
        preg_match_all("/\((((?>[^()]+)|(?R))*)\)/", trim($string), $matches);

        return $matches;
    }

    /**
     * Assemble the parsed nodes into a single filter.
     *
     * @param Node|Node[] $nodes
     *
     * @return string
     */
    public static function assemble($nodes = [])
    {
        $result = '';

        foreach (Arr::wrap($nodes) as $node) {
            $result .= static::compileNode($node);
        }

        return $result;
    }

    /**
     * Assemble the node into its string based format.
     *
     * @param GroupNode|ConditionNode $node
     *
     * @return string
     */
    protected static function compileNode($node)
    {
        switch (true) {
            case $node instanceof GroupNode:
                return static::wrap($node->getOperator().static::assemble($node->getNodes()));
            case $node instanceof ConditionNode:
                return static::wrap($node->getAttribute().$node->getOperator().$node->getValue());
            default:
                throw new ParserException('Unable to assemble filter. Invalid node instance given.');
        }
    }

    /**
     * Build an array of nodes from the given filters.
     *
     * @param string[] $filters
     *
     * @return GroupNode|ConditionNode[]
     */
    protected static function buildNodes(array $filters = [])
    {
        return array_map(function ($filter) {
            return static::isGroup($filter)
                ? new GroupNode($filter)
                : new ConditionNode($filter);
        }, $filters);
    }

    /**
     * Count the open and close parenthesis of the sting.
     *
     * @param string $string
     *
     * @return array
     */
    protected static function countParenthesis($string)
    {
        return [Str::substrCount($string, '('), Str::substrCount($string, ')')];
    }

    /**
     * Wrap the value in parentheses.
     *
     * @param string $value
     *
     * @return string
     */
    protected static function wrap($value)
    {
        return "($value)";
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
        return Str::startsWith($filter, ['&', '|', '!']);
    }
}
