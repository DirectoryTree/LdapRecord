<?php

namespace LdapRecord\Tests\Query\Parser;

use LdapRecord\Query\Filter\ConditionNode;
use LdapRecord\Query\Filter\GroupNode;
use LdapRecord\Query\Filter\Parser;
use LdapRecord\Query\Filter\ParserException;
use LdapRecord\Tests\TestCase;

class ParserTest extends TestCase
{
    public function test_parse_basic()
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

    public function test_parse_nested_groups()
    {
        $group = Parser::parse('(&(objectCategory=person)(objectClass=contact)(|(sn=Smith)(sn=Johnson)))');

        $this->assertInstanceOf(GroupNode::class, $group);
        $this->assertEquals('&', $group->getOperator());

        $this->assertCount(3, $nodes = $group->getNodes());

        $this->assertInstanceOf(ConditionNode::class, $nodes[0]);
        $this->assertEquals('objectCategory', $nodes[0]->getAttribute());
        $this->assertEquals('=', $nodes[0]->getOperator());
        $this->assertEquals('person', $nodes[0]->getValue());

        $this->assertInstanceOf(ConditionNode::class, $nodes[1]);
        $this->assertEquals('objectClass', $nodes[1]->getAttribute());
        $this->assertEquals('=', $nodes[1]->getOperator());
        $this->assertEquals('contact', $nodes[1]->getValue());

        $this->assertInstanceOf(GroupNode::class, $nodes[2]);
        $this->assertEquals('|', $nodes[2]->getOperator());

        $this->assertCount(2, $nodes = $nodes[2]->getNodes());

        $this->assertInstanceOf(ConditionNode::class, $nodes[0]);
        $this->assertEquals('sn', $nodes[0]->getAttribute());
        $this->assertEquals('=', $nodes[0]->getOperator());
        $this->assertEquals('Smith', $nodes[0]->getValue());

        $this->assertInstanceOf(ConditionNode::class, $nodes[1]);
        $this->assertEquals('sn', $nodes[1]->getAttribute());
        $this->assertEquals('=', $nodes[1]->getOperator());
        $this->assertEquals('Johnson', $nodes[1]->getValue());

        $this->assertEquals('(&(objectCategory=person)(objectClass=contact)(|(sn=Smith)(sn=Johnson)))', Parser::assemble($group));
    }

    public function test_parse_throws_exception_when_unclosed_bracket_is_detected()
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Unclosed filter group [:baz:~=zal]');

        Parser::parse('(|(foo=bar):baz:~=zal))');
    }

    public function test_assemble_can_rebuild_parsed_filter()
    {
        $group = Parser::parse('(|(foo=bar)(:baz:~=zal))');

        $this->assertEquals('(|(foo=bar)(:baz:~=zal))', Parser::assemble($group));
    }

    public function test_parser_removes_unneeded_parentheses()
    {
        $group = Parser::parse('(((|(foo=bar)(:baz:~=zal))))');

        $this->assertEquals('(|(foo=bar)(:baz:~=zal))', Parser::assemble($group));
    }
}
