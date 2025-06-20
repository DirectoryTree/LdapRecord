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
     * Get all the available operators.
     */
    public function getOperators(): array
    {
        return array_keys($this->operators);
    }

    /**
     * Wraps a query string in brackets.
     *
     * Produces: (query)
     */
    public function wrap(string $query, ?string $prefix = '(', ?string $suffix = ')'): string
    {
        return $prefix.$query.$suffix;
    }

    /**
     * Compiles the Builder instance into an LDAP query string.
     */
    public function compile(Builder $query): string
    {
        $filter = $this->compileFilters($query);

        return $this->wrapFilterIfNeeded($query, $filter);
    }

    /**
     * Compile all filters for the query.
     */
    protected function compileFilters(Builder $query): string
    {
        return $this->compileRaws($query)
            .$this->compileWheres($query)
            .$this->compileOrWheres($query);
    }

    /**
     * Wrap the filter in logical operators if needed.
     */
    protected function wrapFilterIfNeeded(Builder $query, string $filter): string
    {
        if ($query->isNested()) {
            return $filter;
        }

        // Special case: if we have exactly one AND and one OR, wrap in OR
        if ($this->shouldWrapEntireQueryInOr($query)) {
            return $this->compileOr($filter);
        }

        // If we have multiple filter types, multiple AND conditions, or multiple raw filters, wrap in AND
        if ($this->hasMultipleFilterTypes($query) || $this->hasMultipleAndConditions($query) || $this->hasMultipleRawFilters($query)) {
            return $this->compileAnd($filter);
        }

        // If we only have OR conditions and more than one, wrap in OR
        if ($this->shouldWrapInOr($query)) {
            return $this->compileOr($filter);
        }

        return $filter;
    }

    /**
     * Determine if the query has multiple filter types.
     */
    protected function hasMultipleFilterTypes(Builder $query): bool
    {
        $filterCount = 0;

        foreach (['and', 'or', 'raw'] as $type) {
            if (! empty($query->filters[$type])) {
                $filterCount++;
            }
        }

        return $filterCount > 1;
    }

    /**
     * Determine if the query has multiple AND conditions.
     */
    protected function hasMultipleAndConditions(Builder $query): bool
    {
        return count($query->filters['and'] ?? []) > 1;
    }

    /**
     * Determine if the query has multiple raw filters.
     */
    protected function hasMultipleRawFilters(Builder $query): bool
    {
        return count($query->filters['raw'] ?? []) > 1;
    }

    /**
     * Determine if the entire query should be wrapped in an OR statement.
     */
    protected function shouldWrapEntireQueryInOr(Builder $query): bool
    {
        // If we have exactly one AND condition and one or more OR conditions, wrap the
        // entire query in OR to treat all conditions as alternatives. This handles
        // the common case where a single where() is followed by orWhere() calls.
        return count($query->filters['and'] ?? []) === 1
            && ! empty($query->filters['or'])
            && empty($query->filters['raw']);
    }

    /**
     * Determine if the query should be wrapped in an OR statement.
     */
    protected function shouldWrapInOr(Builder $query): bool
    {
        return ! empty($query->filters['or'])
            && count($query->filters['or']) > 1
            && empty($query->filters['and'])
            && empty($query->filters['raw']);
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
        return $this->compileFilterType($query, 'and');
    }

    /**
     * Assembles all or where clauses in the current orWheres property.
     */
    protected function compileOrWheres(Builder $query): string
    {
        $filter = $this->compileFilterType($query, 'or');

        // If we're going to wrap the entire query in OR, don't wrap OR clauses separately
        if ($this->shouldWrapEntireQueryInOr($query)) {
            return $filter;
        }

        // If we have OR clauses and other filter types (mixed query),
        // wrap the OR clauses in their own OR statement
        if (! empty($filter) && $this->hasMultipleFilterTypes($query)) {
            return $this->compileOr($filter);
        }

        return $filter;
    }

    /**
     * Compile filters of a specific type.
     */
    protected function compileFilterType(Builder $query, string $type): string
    {
        $filter = '';

        foreach ($query->filters[$type] ?? [] as $where) {
            $filter .= $this->compileWhere($where);
        }

        return $filter;
    }

    /**
     * Concatenates filters into a single string.
     */
    public function concatenate(array $bindings = []): string
    {
        return implode(
            array_filter($bindings, fn (mixed $value) => ! empty($value))
        );
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
     * Returns a query string for starts with.
     *
     * Produces: (attribute=value*)
     */
    public function compileStartsWith(string $attribute, string $value): string
    {
        return $this->wrap("$attribute=$value*");
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
     * Wraps the inserted query inside an AND operator.
     *
     * Produces: (&query)
     */
    public function compileAnd(string $query): string
    {
        return $query ? $this->wrap($query, '(&') : '';
    }

    /**
     * Wraps the inserted query inside an OR operator.
     *
     * Produces: (|query)
     */
    public function compileOr(string $query): string
    {
        return $query ? $this->wrap($query, '(|') : '';
    }

    /**
     * Wraps the inserted query inside an NOT operator.
     */
    public function compileNot(string $query): string
    {
        return $query ? $this->wrap($query, '(!') : '';
    }
}
