<?php

namespace LdapRecord\Tests\Query;

use LdapRecord\Query\Grammar;
use LdapRecord\Tests\TestCase;

class GrammarTest extends TestCase
{
    public function newGrammar()
    {
        return new Grammar();
    }

    public function test_wrap()
    {
        $g = $this->newGrammar();

        $wrapped = $g->wrap('test');

        $expected = '(test)';

        $this->assertEquals($expected, $wrapped);
    }

    public function test_wrap_prefix()
    {
        $g = $this->newGrammar();

        $wrapped = $g->wrap('test', '(!');

        $expected = '(!test)';

        $this->assertEquals($expected, $wrapped);
    }

    public function test_wrap_suffix()
    {
        $g = $this->newGrammar();

        $wrapped = $g->wrap('test', null, '=)');

        $expected = 'test=)';

        $this->assertEquals($expected, $wrapped);
    }

    public function test_wrap_both()
    {
        $g = $this->newGrammar();

        $wrapped = $g->wrap('test', '(!prefix', 'suffix)');

        $expected = '(!prefixtestsuffix)';

        $this->assertEquals($expected, $wrapped);
    }

    public function test_all_operators()
    {
        $expected = [
            '*', '!*', '=', '!', '!=', '>=', '<=',
            '~=', 'starts_with', 'not_starts_with',
            'ends_with', 'not_ends_with', 'contains', 'not_contains',
        ];

        $this->assertEquals($expected, $this->newGrammar()->getOperators());
    }
}
