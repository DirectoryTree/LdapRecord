<?php

namespace LdapRecord\Tests\Query\Parser;

use LdapRecord\Query\Filter\ConditionNode;
use LdapRecord\Query\Filter\GroupNode;
use LdapRecord\Query\Filter\Parser;
use LdapRecord\Tests\TestCase;

class ParserTest extends TestCase
{
    public function test()
    {
        $group = Parser::parse('(|(foo=bar)(:baz:~=zal))');

        $this->assertInstanceOf(GroupNode::class, $group);
        $this->assertEquals('|', $group->getOperator());

        $this->assertCount(2, $nodes = $group->getNodes());

        $this->assertInstanceOf(ConditionNode::class, $nodes[0]);
        $this->assertEquals('foo', $nodes[0]->getAttribute());
        $this->assertEquals('=', $nodes[0]->getOperator());
        $this->assertEquals('bar', $nodes[0]->getValue());

        $this->assertInstanceOf(ConditionNode::class, $nodes[1]);
        $this->assertEquals(':baz:', $nodes[1]->getAttribute());
        $this->assertEquals('~=', $nodes[1]->getOperator());
        $this->assertEquals('zal', $nodes[1]->getValue());
    }
}
