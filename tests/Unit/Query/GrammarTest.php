<?php

namespace LdapRecord\Tests\Unit\Query;

use LdapRecord\Query\Grammar;
use LdapRecord\Tests\TestCase;

class GrammarTest extends TestCase
{
    public function test_all_operators()
    {
        $expected = [
            '*', '!*', '=', '!', '!=', '>=', '<=',
            '~=', 'starts_with', 'not_starts_with',
            'ends_with', 'not_ends_with', 'contains', 'not_contains',
        ];

        $this->assertEquals($expected, (new Grammar)->operators());
    }
}
