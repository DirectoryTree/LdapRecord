<?php

namespace LdapRecord\Tests\Unit\Query\Filter;

use LdapRecord\Query\Filter\Equals;
use LdapRecord\Query\Filter\Factory;
use LdapRecord\Query\Filter\Has;
use LdapRecord\Query\Filter\Not;
use LdapRecord\Tests\TestCase;
use UnexpectedValueException;

class FactoryTest extends TestCase
{
    public function test_all_operators()
    {
        $expected = [
            '*', '!*', '=', '!', '!=', '>=', '<=',
            '~=', 'starts_with', 'not_starts_with',
            'ends_with', 'not_ends_with', 'contains', 'not_contains',
        ];

        $this->assertEquals($expected, Factory::operators());
    }

    public function test_make_filter()
    {
        $filter = Factory::make('=', 'cn', 'John Doe');

        $this->assertInstanceOf(Equals::class, $filter);
        $this->assertEquals('cn', $filter->getAttribute());
        $this->assertEquals('=', $filter->getOperator());
        $this->assertEquals('John Doe', $filter->getValue());
    }

    public function test_make_filter_with_negated_operator()
    {
        $filter = Factory::make('!=', 'cn', 'John Doe');

        $this->assertInstanceOf(Not::class, $filter);
        $this->assertInstanceOf(Equals::class, $filter->getFilter());
    }

    public function test_make_filter_with_has_operator()
    {
        $filter = Factory::make('*', 'cn');

        $this->assertInstanceOf(Has::class, $filter);
    }

    public function test_make_filter_with_not_has_operator()
    {
        $filter = Factory::make('!*', 'cn');

        $this->assertInstanceOf(Not::class, $filter);
        $this->assertInstanceOf(Has::class, $filter->getFilter());
    }

    public function test_make_filter_with_invalid_operator()
    {
        $this->expectException(UnexpectedValueException::class);

        Factory::make('foo', 'cn');
    }
}
