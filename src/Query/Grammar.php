<?php

namespace LdapRecord\Query;

use UnexpectedValueException;

class Grammar
{
    /**
     * The query operators and their method names.
     */
    public array $operators = [
        '*' => 'has',
        '!*' => 'notHas',
        '=' => 'equals',
        '!' => 'doesNotEqual',
        '!=' => 'doesNotEqual',
        '>=' => 'greaterThanOrEquals',
        '<=' => 'lessThanOrEquals',
        '~=' => 'approximatelyEquals',
        'starts_with' => 'startsWith',
        'not_starts_with' => 'notStartsWith',
        'ends_with' => 'endsWith',
        'not_ends_with' => 'notEndsWith',
        'contains' => 'contains',
        'not_contains' => 'notContains',
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
        $filter = $this->compileWheres($query).$this->compileRaws($query);

        if ($query->isNested()) {
            return $filter;
        }

        if ($this->shouldWrapEntireQueryInAnd($query)) {
            return $this->compileAnd($filter);
        }

        return $filter;
    }

    /**
     * Determine if the entire query should be wrapped in an AND statement.
     */
    protected function shouldWrapEntireQueryInAnd(Builder $query): bool
    {
        $count = 0;

        foreach (['and', 'or', 'raw'] as $type) {
            if (! empty($query->filters[$type])) {
                $count++;
            }
        }

        return $count > 1;
    }

    /**
     * Assembles all the "raw" filters on the query.
     */
    protected function compileRaws(Builder $query): string
    {
        return $this->concatenate($query->filters['raw'] ?? []);
    }

    /**
     * Assembles all where clauses in the current wheres property.
     */
    protected function compileWheres(Builder $query): string
    {
        if ($this->shouldWrapWheresInOr($query)) {
            return $this->compileOr(
                $this->concatenate([
                    ...array_map($this->compileWhere(...), $query->filters['and']),
                    ...array_map($this->compileWhere(...), $query->filters['or']),
                ])
            );
        }

        if ($this->shouldSegmentWheresInAndOr($query)) {
            return $this->compileAnd(
                $this->concatenate(array_map($this->compileWhere(...), $query->filters['and']))
            ).$this->compileOr(
                $this->concatenate(array_map($this->compileWhere(...), $query->filters['or']))
            );
        }

        return $this->concatenate([
            ...array_map($this->compileWhere(...), $query->filters['and']),
            ...array_map($this->compileWhere(...), $query->filters['or']),
        ]);
    }

    protected function shouldSegmentWheresInAndOr(Builder $query): bool
    {
        return count($query->filters['and']) > 1 && count($query->filters['or']) > 1;
    }

    /**
     * Determine if the query should be wrapped in an OR statement.
     */
    protected function shouldWrapWheresInOr(Builder $query): bool
    {
        // If we have exactly one AND condition and one or more OR conditions, wrap the
        // entire query in OR to treat all conditions as alternatives. This handles
        // the common case where a single where() is followed by orWhere() calls.
        if (count($query->filters['and']) === 1 && count($query->filters['or']) >= 1) {
            return true;
        }

        return count($query->filters['or']) > 1 && empty($query->filters['and']);
    }

    /**
     * Assembles a single where query.
     *
     * @throws UnexpectedValueException
     */
    protected function compileWhere(array $where): string
    {
        $method = $this->makeCompileMethod($where['operator']);

        // Some operators like 'has' and 'notHas' don't require a value
        if (in_array($where['operator'], ['*', '!*'])) {
            return $this->{$method}($where['attribute']);
        }

        return $this->{$method}($where['attribute'], $where['value']);
    }

    /**
     * Make the compile method name for the operator.
     *
     * @throws UnexpectedValueException
     */
    protected function makeCompileMethod(string $operator): string
    {
        if (! $this->operatorExists($operator)) {
            throw new UnexpectedValueException("Invalid LDAP filter operator ['$operator']");
        }

        return 'compile'.ucfirst($this->operators[$operator]);
    }

    /**
     * Determine if the operator exists.
     */
    protected function operatorExists(string $operator): bool
    {
        return array_key_exists($operator, $this->operators);
    }

    /**
     * Returns a query string for equals.
     *
     * Produces: (attribute=value)
     */
    public function compileEquals(string $attribute, string $value): string
    {
        return $this->wrap($attribute.'='.$value);
    }

    /**
     * Returns a query string for does not equal.
     *
     * Produces: (!(attribute=value))
     */
    public function compileDoesNotEqual(string $attribute, string $value): string
    {
        return $this->compileNot(
            $this->compileEquals($attribute, $value)
        );
    }

    /**
     * Alias for does not equal operator (!=) operator.
     *
     * Produces: (!(attribute=value))
     */
    public function compileDoesNotEqualAlias(string $attribute, string $value): string
    {
        return $this->compileDoesNotEqual($attribute, $value);
    }

    /**
     * Returns a query string for greater than or equals.
     *
     * Produces: (attribute>=value)
     */
    public function compileGreaterThanOrEquals(string $attribute, string $value): string
    {
        return $this->wrap("$attribute>=$value");
    }

    /**
     * Returns a query string for less than or equals.
     *
     * Produces: (attribute<=value)
     */
    public function compileLessThanOrEquals(string $attribute, string $value): string
    {
        return $this->wrap("$attribute<=$value");
    }

    /**
     * Returns a query string for approximately equals.
     *
     * Produces: (attribute~=value)
     */
    public function compileApproximatelyEquals(string $attribute, string $value): string
    {
        return $this->wrap("$attribute~=$value");
    }

    /**
     * Returns a query string for does not start with.
     *
     * Produces: (!(attribute=*value))
     */
    public function compileNotStartsWith(string $attribute, string $value): string
    {
        return $this->compileNot(
            $this->compileStartsWith($attribute, $value)
        );
    }

    /**
     * Returns a query string for starts with.
     *
     * Produces: (attribute=value*)
     */
    public function compileStartsWith(string $attribute, string $value): string
    {
        return $this->wrap("$attribute=$value*");
    }

    /**
     * Returns a query string for ends with.
     *
     * Produces: (attribute=*value)
     */
    public function compileEndsWith(string $attribute, string $value): string
    {
        return $this->wrap("$attribute=*$value");
    }

    /**
     * Returns a query string for does not end with.
     *
     * Produces: (!(attribute=value*))
     */
    public function compileNotEndsWith(string $attribute, string $value): string
    {
        return $this->compileNot($this->compileEndsWith($attribute, $value));
    }

    /**
     * Returns a query string for contains.
     *
     * Produces: (attribute=*value*)
     */
    public function compileContains(string $attribute, string $value): string
    {
        return $this->wrap("$attribute=*$value*");
    }

    /**
     * Returns a query string for does not contain.
     *
     * Produces: (!(attribute=*value*))
     */
    public function compileNotContains(string $attribute, string $value): string
    {
        return $this->compileNot(
            $this->compileContains($attribute, $value)
        );
    }

    /**
     * Returns a query string for a where has.
     *
     * Produces: (attribute=*)
     */
    public function compileHas(string $attribute): string
    {
        return $this->wrap("$attribute=*");
    }

    /**
     * Returns a query string for a where does not have.
     *
     * Produces: (!(attribute=*))
     */
    public function compileNotHas(string $attribute): string
    {
        return $this->compileNot(
            $this->compileHas($attribute)
        );
    }

    /**
     * Wrap the inserted query inside an AND operator.
     *
     * Produces: (&query)
     */
    public function compileAnd(string $query): string
    {
        return $query ? $this->wrap($query, '(&') : '';
    }

    /**
     * Wrap the inserted query inside an OR operator.
     *
     * Produces: (|query)
     */
    public function compileOr(string $query): string
    {
        return $query ? $this->wrap($query, '(|') : '';
    }

    /**
     * Wrap the inserted query inside an NOT operator.
     */
    public function compileNot(string $query): string
    {
        return $query ? $this->wrap($query, '(!') : '';
    }

    /**
     * Wrap a query string in brackets.
     *
     * Produces: (query)
     */
    public function wrap(string $query, ?string $prefix = '(', ?string $suffix = ')'): string
    {
        return $prefix.$query.$suffix;
    }

    /**
     * Concatenate filters into a single string.
     */
    public function concatenate(array $filters = []): string
    {
        return implode(
            array_filter($filters, fn (mixed $value) => ! empty($value))
        );
    }
}
