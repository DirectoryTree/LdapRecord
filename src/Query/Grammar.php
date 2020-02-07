<?php

namespace LdapRecord\Query;

use UnexpectedValueException;

class Grammar
{
    /**
     * The query operators and their method names.
     *
     * @var array
     */
    public $operators = [
        '*'               => 'has',
        '!*'              => 'notHas',
        '='               => 'equals',
        '!'               => 'doesNotEqual',
        '!='              => 'doesNotEqual',
        '>='              => 'greaterThanOrEquals',
        '<='              => 'lessThanOrEquals',
        '~='              => 'approximatelyEquals',
        'starts_with'     => 'startsWith',
        'not_starts_with' => 'notStartsWith',
        'ends_with'       => 'endsWith',
        'not_ends_with'   => 'notEndsWith',
        'contains'        => 'contains',
        'not_contains'    => 'notContains',
    ];

    /**
     * Get all the available operators.
     *
     * @return array
     */
    public function getOperators()
    {
        return array_keys($this->operators);
    }

    /**
     * Wraps a query string in brackets.
     *
     * Produces: (query)
     *
     * @param string $query
     * @param string $prefix
     * @param string $suffix
     *
     * @return string
     */
    public function wrap($query, $prefix = '(', $suffix = ')')
    {
        return $prefix.$query.$suffix;
    }

    /**
     * Compiles the Builder instance into an LDAP query string.
     *
     * @param Builder $builder
     *
     * @return string
     */
    public function compile(Builder $builder)
    {
        $query = $this->generateAndConcatenate($builder);

        if ($builder->isNested()) {
            return $query;
        }

        $ands = count($builder->filters['and']);

        // If multiple filters are being used, we must
        // wrap the filter in an "and" statement to
        // make sure a valid filter is generated.
        if ($ands + count($builder->filters['raw']) > 1) {
            $query = $this->compileAnd($query);
        }
        // This is also the case if we only have a
        // single "and" statement but are using
        // any "or" statements in the filter.
        else if($ands === 1 && count($builder->filters['or']) > 0) {
            $query = $this->compileAnd($query);
        }

        return $query;
    }

    /**
     * Generate and concatenate the query filter.
     *
     * @param Builder $query
     *
     * @return string
     */
    protected function generateAndConcatenate(Builder $query)
    {
        return $this->compileOrWheres(
            $query->filters['or'],
            $this->compileWheres(
                $query->filters['and'],
                $this->concatenate($query->filters['raw'])
            )
        );
    }

    /**
     * Concatenates filters into a single string.
     *
     * @param array $bindings
     *
     * @return string
     */
    public function concatenate(array $bindings = [])
    {
        // Filter out empty query segments.
        $bindings = array_filter($bindings, function ($value) {
            return (string) $value !== '';
        });

        return implode('', $bindings);
    }

    /**
     * Returns a query string for equals.
     *
     * Produces: (field=value)
     *
     * @param string $field
     * @param string $value
     *
     * @return string
     */
    public function compileEquals($field, $value)
    {
        return $this->wrap($field.'='.$value);
    }

    /**
     * Returns a query string for does not equal.
     *
     * Produces: (!(field=value))
     *
     * @param string $field
     * @param string $value
     *
     * @return string
     */
    public function compileDoesNotEqual($field, $value)
    {
        return $this->compileNot($this->compileEquals($field, $value));
    }

    /**
     * Alias for does not equal operator (!=) operator.
     *
     * Produces: (!(field=value))
     *
     * @param string $field
     * @param string $value
     *
     * @return string
     */
    public function compileDoesNotEqualAlias($field, $value)
    {
        return $this->compileDoesNotEqual($field, $value);
    }

    /**
     * Returns a query string for greater than or equals.
     *
     * Produces: (field>=value)
     *
     * @param string $field
     * @param string $value
     *
     * @return string
     */
    public function compileGreaterThanOrEquals($field, $value)
    {
        return $this->wrap("$field>=$value");
    }

    /**
     * Returns a query string for less than or equals.
     *
     * Produces: (field<=value)
     *
     * @param string $field
     * @param string $value
     *
     * @return string
     */
    public function compileLessThanOrEquals($field, $value)
    {
        return $this->wrap("$field<=$value");
    }

    /**
     * Returns a query string for approximately equals.
     *
     * Produces: (field~=value)
     *
     * @param string $field
     * @param string $value
     *
     * @return string
     */
    public function compileApproximatelyEquals($field, $value)
    {
        return $this->wrap("$field~=$value");
    }

    /**
     * Returns a query string for starts with.
     *
     * Produces: (field=value*)
     *
     * @param string $field
     * @param string $value
     *
     * @return string
     */
    public function compileStartsWith($field, $value)
    {
        return $this->wrap("$field=$value*");
    }

    /**
     * Returns a query string for does not start with.
     *
     * Produces: (!(field=*value))
     *
     * @param string $field
     * @param string $value
     *
     * @return string
     */
    public function compileNotStartsWith($field, $value)
    {
        return $this->compileNot($this->compileStartsWith($field, $value));
    }

    /**
     * Returns a query string for ends with.
     *
     * Produces: (field=*value)
     *
     * @param string $field
     * @param string $value
     *
     * @return string
     */
    public function compileEndsWith($field, $value)
    {
        return $this->wrap("$field=*$value");
    }

    /**
     * Returns a query string for does not end with.
     *
     * Produces: (!(field=value*))
     *
     * @param string $field
     * @param string $value
     *
     * @return string
     */
    public function compileNotEndsWith($field, $value)
    {
        return $this->compileNot($this->compileEndsWith($field, $value));
    }

    /**
     * Returns a query string for contains.
     *
     * Produces: (field=*value*)
     *
     * @param string $field
     * @param string $value
     *
     * @return string
     */
    public function compileContains($field, $value)
    {
        return $this->wrap("$field=*$value*");
    }

    /**
     * Returns a query string for does not contain.
     *
     * Produces: (!(field=*value*))
     *
     * @param string $field
     * @param string $value
     *
     * @return string
     */
    public function compileNotContains($field, $value)
    {
        return $this->compileNot($this->compileContains($field, $value));
    }

    /**
     * Returns a query string for a where has.
     *
     * Produces: (field=*)
     *
     * @param string $field
     *
     * @return string
     */
    public function compileHas($field)
    {
        return $this->wrap("$field=*");
    }

    /**
     * Returns a query string for a where does not have.
     *
     * Produces: (!(field=*))
     *
     * @param string $field
     *
     * @return string
     */
    public function compileNotHas($field)
    {
        return $this->compileNot($this->compileHas($field));
    }

    /**
     * Wraps the inserted query inside an AND operator.
     *
     * Produces: (&query)
     *
     * @param string $query
     *
     * @return string
     */
    public function compileAnd($query)
    {
        return $query ? $this->wrap($query, '(&') : '';
    }

    /**
     * Wraps the inserted query inside an OR operator.
     *
     * Produces: (|query)
     *
     * @param string $query
     *
     * @return string
     */
    public function compileOr($query)
    {
        return $query ? $this->wrap($query, '(|') : '';
    }

    /**
     * Wraps the inserted query inside an NOT operator.
     *
     * @param string $query
     *
     * @return string
     */
    public function compileNot($query)
    {
        return $query ? $this->wrap($query, '(!') : '';
    }

    /**
     * Assembles all where clauses in the current wheres property.
     *
     * @param array  $wheres
     * @param string $query
     *
     * @return string
     */
    protected function compileWheres(array $wheres = [], $query = '')
    {
        foreach ($wheres as $where) {
            $query .= $this->compileWhere($where);
        }

        return $query;
    }

    /**
     * Assembles all or where clauses in the current orWheres property.
     *
     * @param array  $orWheres
     * @param string $query
     *
     * @return string
     */
    protected function compileOrWheres(array $orWheres = [], $query = '')
    {
        $or = '';

        foreach ($orWheres as $where) {
            $or .= $this->compileWhere($where);
        }

        // Here we will make sure to wrap the query in an
        // "or" filter if multiple "or" statements are
        // given to ensure it's created properly.
        if (($query && count($orWheres) > 0) || count($orWheres) > 1) {
            $query .= $this->compileOr($or);
        } else {
            $query .= $or;
        }

        return $query;
    }

    /**
     * Assembles a single where query.
     *
     * @param array $where
     *
     * @throws UnexpectedValueException
     *
     * @return string
     */
    protected function compileWhere(array $where)
    {
        if (array_key_exists($where['operator'], $this->operators)) {
            $method = 'compile'.ucfirst($this->operators[$where['operator']]);

            return $this->{$method}($where['field'], $where['value']);
        }

        throw new UnexpectedValueException('Invalid LDAP filter operator ['.$where['operator'].']');
    }
}
