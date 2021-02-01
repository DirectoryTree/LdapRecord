<?php

namespace LdapRecord\Tests\Testing;

use LdapRecord\Tests\TestCase;
use LdapRecord\Testing\LdapExpectation;
use PHPUnit\Framework\Constraint\Constraint;
use PHPUnit\Framework\ExpectationFailedException;

class LdapExpectationTest extends TestCase
{
    public function test_method_is_properly_returned()
    {
        $this->assertEquals('method', (new LdapExpectation('method'))->getMethod());
    }

    public function test_expected_args_are_transformed_into_constraints()
    {
        $expectation = (new LdapExpectation('method'))->with('one', 'two');

        $this->assertCount(2, $args = $expectation->getExpectedArgs());
        $this->assertInstanceOf(Constraint::class, $args[0]);
        $this->assertInstanceOf(Constraint::class, $args[1]);

        $this->assertTrue($args[0]->evaluate('one'));

        $this->expectException(ExpectationFailedException::class);

        $args[1]->evaluate('invalid');
    }

    public function test_expected_return_value_is_properly_returned()
    {
        $expectation = (new LdapExpectation('method'))->andReturn('value');

        $this->assertEquals('value', $expectation->getExpectedValue());
    }

    public function test_decrementing_call_count_decrements_value()
    {
        $expectation = (new LdapExpectation('method'))->times(3);

        $expectation->decrementCallCount();

        $this->assertEquals(2, $expectation->getExpectedCount());
    }

    public function test_decrementing_call_count_does_not_decrement_when_count_has_not_been_defined()
    {
        $expectation = (new LdapExpectation('method'));

        $this->assertEquals(1, $expectation->getExpectedCount());

        $expectation->decrementCallCount();
        $expectation->decrementCallCount();
        $expectation->decrementCallCount();

        $this->assertEquals(1, $expectation->getExpectedCount());
    }
}
