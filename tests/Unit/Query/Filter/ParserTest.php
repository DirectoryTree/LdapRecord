<?php

namespace LdapRecord\Tests\Unit\Query\Filter;

use LdapRecord\Query\Filter\AndGroup;
use LdapRecord\Query\Filter\ApproximatelyEquals;
use LdapRecord\Query\Filter\Equals;
use LdapRecord\Query\Filter\OrGroup;
use LdapRecord\Query\Filter\Parser;
use LdapRecord\Query\Filter\ParserException;
use LdapRecord\Tests\TestCase;
use TypeError;

class ParserTest extends TestCase
{
    public function test_parsing_basic_filter()
    {
        $group = Parser::parse('(|(foo=bar)(:baz:~=zal))')[0];

        $this->assertInstanceOf(OrGroup::class, $group);
        $this->assertEquals('|', $group->getOperator());

        $this->assertCount(2, $filters = $group->getFilters());

        $this->assertInstanceOf(Equals::class, $filters[0]);
        $this->assertEquals('foo', $filters[0]->getAttribute());
        $this->assertEquals('=', $filters[0]->getOperator());
        $this->assertEquals('bar', $filters[0]->getValue());

        $this->assertInstanceOf(ApproximatelyEquals::class, $filters[1]);
        $this->assertEquals(':baz:', $filters[1]->getAttribute());
        $this->assertEquals('~=', $filters[1]->getOperator());
        $this->assertEquals('zal', $filters[1]->getValue());
    }

    public function test_parsing_badly_formatted_filter()
    {
        $filters = Parser::parse('(foo=bar)_~@#-foobar-.~=(:baz:~=zal)');

        $this->assertCount(2, $filters);

        $this->assertInstanceOf(Equals::class, $filters[0]);
        $this->assertEquals('foo', $filters[0]->getAttribute());
        $this->assertEquals('=', $filters[0]->getOperator());
        $this->assertEquals('bar', $filters[0]->getValue());

        $this->assertInstanceOf(ApproximatelyEquals::class, $filters[1]);
        $this->assertEquals(':baz:', $filters[1]->getAttribute());
        $this->assertEquals('~=', $filters[1]->getOperator());
        $this->assertEquals('zal', $filters[1]->getValue());
    }

    public function test_parsing_nested_filter_groups()
    {
        $group = Parser::parse('(&(objectCategory=person)(objectClass=contact)(|(sn=Smith)(sn=Johnson)))')[0];

        $this->assertInstanceOf(AndGroup::class, $group);
        $this->assertEquals('&', $group->getOperator());

        $this->assertCount(3, $filters = $group->getFilters());

        $this->assertInstanceOf(Equals::class, $filters[0]);
        $this->assertEquals('objectCategory', $filters[0]->getAttribute());
        $this->assertEquals('=', $filters[0]->getOperator());
        $this->assertEquals('person', $filters[0]->getValue());

        $this->assertInstanceOf(Equals::class, $filters[1]);
        $this->assertEquals('objectClass', $filters[1]->getAttribute());
        $this->assertEquals('=', $filters[1]->getOperator());
        $this->assertEquals('contact', $filters[1]->getValue());

        $this->assertInstanceOf(OrGroup::class, $filters[2]);
        $this->assertEquals('|', $filters[2]->getOperator());

        $this->assertCount(2, $nestedFilters = $filters[2]->getFilters());

        $this->assertInstanceOf(Equals::class, $nestedFilters[0]);
        $this->assertEquals('sn', $nestedFilters[0]->getAttribute());
        $this->assertEquals('=', $nestedFilters[0]->getOperator());
        $this->assertEquals('Smith', $nestedFilters[0]->getValue());

        $this->assertInstanceOf(Equals::class, $nestedFilters[1]);
        $this->assertEquals('sn', $nestedFilters[1]->getAttribute());
        $this->assertEquals('=', $nestedFilters[1]->getOperator());
        $this->assertEquals('Johnson', $nestedFilters[1]->getValue());

        $this->assertEquals('(&(objectCategory=person)(objectClass=contact)(|(sn=Smith)(sn=Johnson)))', Parser::assemble($group));
    }

    public function test_parser_can_parse_value_with_equal_sign()
    {
        $filters = Parser::parse('(&(objectClass=inetOrgPerson)(memberof=cn=foo,ou=Groups,dc=example,dc=org))');

        $this->assertCount(1, $filters);
        $this->assertInstanceOf(AndGroup::class, $filters[0]);

        $this->assertCount(2, $filters[0]->getFilters());

        $groupFilters = $filters[0]->getFilters();
        $this->assertInstanceOf(Equals::class, $groupFilters[0]);
        $this->assertEquals('objectClass', $groupFilters[0]->getAttribute());
        $this->assertEquals('=', $groupFilters[0]->getOperator());
        $this->assertEquals('inetOrgPerson', $groupFilters[0]->getValue());

        $this->assertInstanceOf(Equals::class, $groupFilters[1]);
        $this->assertEquals('memberof', $groupFilters[1]->getAttribute());
        $this->assertEquals('=', $groupFilters[1]->getOperator());
        $this->assertEquals('cn=foo,ou=Groups,dc=example,dc=org', $groupFilters[1]->getValue());
    }

    public function test_parser_throws_exception_when_missing_open_parenthesis_is_detected()
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Unclosed filter group. Missing "(" parenthesis');

        Parser::parse('(|(foo=bar)(:baz:~=zal)))');
    }

    public function test_parser_throws_exception_when_missing_close_parenthesis_is_detected()
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Unclosed filter group. Missing ")" parenthesis');

        Parser::parse('((|(foo=bar)(:baz:~=zal))');
    }

    public function test_parser_throws_exception_when_unclosed_nested_filter_is_detected()
    {
        $this->expectException(ParserException::class);
        $this->expectExceptionMessage('Unclosed filter group [:baz:~=zal]');

        Parser::parse('((|(foo=bar):baz:~=zal))');
    }

    public function test_assemble_can_rebuild_parsed_filter()
    {
        $filters = Parser::parse('(|(foo=bar)(:baz:~=zal))');

        $this->assertEquals('(|(foo=bar)(:baz:~=zal))', Parser::assemble($filters));
    }

    public function test_parser_removes_unneeded_parentheses()
    {
        $filters = Parser::parse('(((|(foo=bar)(:baz:~=zal))))');

        $this->assertEquals('(|(foo=bar)(:baz:~=zal))', Parser::assemble($filters));
    }

    public function test_parser_removes_unneeded_spaces()
    {
        $filters = Parser::parse('   (  |( foo=bar)( :baz:~=zal )   )   ');

        $this->assertEquals('(|(foo=bar)(:baz:~=zal))', Parser::assemble($filters));
    }

    public function test_parser_preserves_value_spaces()
    {
        $filters = Parser::parse('(|(foo=bar baz zal))');

        $this->assertEquals('(|(foo=bar baz zal))', Parser::assemble($filters));
    }

    public function test_parser_can_process_multiple_root_filters()
    {
        $filters = Parser::parse('(cn=Steve)(sn=Bauman)');

        $this->assertCount(2, $filters);

        $this->assertEquals('cn=Steve', $filters[0]->getRaw());
        $this->assertEquals('sn=Bauman', $filters[1]->getRaw());
    }

    public function test_parser_can_parse_multiple_root_group_filters()
    {
        $filters = Parser::parse('(|(foo=bar))(|(&(cn=Steve)(sn=Bauman))(mail=sbauman@local.com))');

        $this->assertCount(2, $filters);

        $this->assertInstanceOf(OrGroup::class, $filters[0]);
        $this->assertInstanceOf(OrGroup::class, $filters[1]);

        $this->assertEquals('|(foo=bar)', $filters[0]->getRaw());
        $this->assertEquals('|(&(cn=Steve)(sn=Bauman))(mail=sbauman@local.com)', $filters[1]->getRaw());
    }

    public function test_parser_can_process_single_filter()
    {
        $filter = Parser::parse('(foo=bar)')[0];

        $this->assertEquals('(foo=bar)', Parser::assemble($filter));
    }

    public function test_parser_throws_exception_during_assemble_when_invalid_filters_given()
    {
        $this->expectException(TypeError::class);

        Parser::assemble(['foo', 'bar']);
    }
}
