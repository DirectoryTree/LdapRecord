<?php

namespace LdapRecord\Tests\Unit\Testing;

use Exception;
use LdapRecord\LdapResultResponse;
use LdapRecord\Testing\LdapExpectation;
use LdapRecord\Testing\LdapExpectationException;
use LdapRecord\Testing\LdapFake;
use LdapRecord\Tests\TestCase;
use PHPUnit\Framework\Constraint\IsAnything;
use PHPUnit\Framework\Constraint\IsEqual;

class LdapFakeTest extends TestCase
{
    public function test_operation()
    {
        $fake = new LdapFake;

        $operation = $fake->operation('foo');

        $this->assertInstanceOf(LdapExpectation::class, $operation);
        $this->assertEquals('foo', $operation->getMethod());
    }

    public function test_should_allow_bind_with()
    {
        $fake = new LdapFake;

        $fake->shouldAllowBindWith('foo');

        $this->assertTrue($fake->hasExpectations('bind'));

        $expectation = $fake->getExpectations('bind')[0];

        $this->assertEquals('bind', $expectation->getMethod());
        $this->assertInstanceOf(IsEqual::class, $expectation->getExpectedArgs()[0]);
        $this->assertInstanceOf(IsAnything::class, $expectation->getExpectedArgs()[1]);
    }

    public function test_expect()
    {
        $fake = new LdapFake;

        $fake->expect(['foo' => 'bar']);
        $fake->expect($fake->operation('bar'));
        $fake->expect(new LdapExpectation('baz'));

        $this->assertTrue($fake->hasExpectations('foo'));
        $this->assertTrue($fake->hasExpectations('bar'));
        $this->assertTrue($fake->hasExpectations('baz'));
    }

    public function test_remove_expectation()
    {
        $fake = new LdapFake;

        $fake->expect([
            'foo' => 'bar',
            $fake->operation('foo'),
        ]);

        $this->assertTrue($fake->hasExpectations('foo'));

        $fake->removeExpectation('foo', 0);

        $this->assertTrue($fake->hasExpectations('foo'));

        $fake->removeExpectation('foo', 1);

        $this->assertFalse($fake->hasExpectations('foo'));
    }

    public function test_should_return_error_number()
    {
        $fake = new LdapFake;

        $fake->shouldReturnErrorNumber(10);

        $this->assertEquals(10, $fake->errNo());
    }

    public function test_should_return_error()
    {
        $fake = new LdapFake;

        $fake->shouldReturnError('foo');

        $this->assertEquals('foo', $fake->getLastError());
    }

    public function test_should_return_diagnostic_message()
    {
        $fake = new LdapFake;

        $fake->shouldReturnDiagnosticMessage('foo');

        $this->assertEquals('foo', $fake->getDiagnosticMessage());
    }

    public function test_connect()
    {
        $fake = new LdapFake;

        $this->assertTrue($fake->connect('host', 389));

        $fake = new LdapFake;

        $fake->expect(['connect' => false]);

        $this->assertFalse($fake->connect('host', 389));
    }

    public function test_bind_without_expectation_throws_exception()
    {
        $fake = new LdapFake;

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('LDAP method [bind] was unexpected.');

        $fake->bind('foo', 'bar');
    }

    public function test_bind_with_expectation_returns_result()
    {
        $fake = new LdapFake;

        $fake->expect(['bind' => $response = new LdapResultResponse]);

        $this->assertSame($response, $fake->bind('foo', 'bar'));

        $this->assertTrue($fake->isBound());
    }

    public function test_bind_with_expectation_returns_failed_result()
    {
        $fake = new LdapFake;

        $fake->expect(['bind' => $response = new LdapResultResponse(1)]);

        $this->assertSame($response, $fake->bind('foo', 'bar'));

        $this->assertFalse($fake->isBound());
    }

    public function test_close()
    {
        $fake = new LdapFake;

        $this->assertTrue($fake->close());

        $fake = new LdapFake;

        $fake->expect(['close' => false]);

        $this->assertFalse($fake->close());
    }

    public function test_get_entries()
    {
        $this->assertEquals(['foo', 'bar'], (new LdapFake)->getEntries(['foo', 'bar']));
    }

    public function test_assert_minimum_expectation_counts_does_not_throw_exception_on_indefinite_expectations()
    {
        $fake = (new LdapFake)->expect(
            $expectation = LdapFake::operation('foo')
        );

        $this->assertTrue($expectation->isIndefinite());

        $fake->assertMinimumExpectationCounts();
    }

    public function test_assert_minimum_expectation_counts_throws_exception_on_expected_count_not_met()
    {
        $fake = (new LdapFake)->expect(
            $expectation = LdapFake::operation('foo')->once()
        );

        $this->assertFalse($expectation->isIndefinite());

        $this->expectException(LdapExpectationException::class);
        $this->expectExceptionMessage('Method [foo] should be called 1 times but was called 0 times');

        $fake->assertMinimumExpectationCounts();
    }
}
