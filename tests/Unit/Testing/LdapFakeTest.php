<?php

namespace LdapRecord\Tests\Unit\Testing;

use Exception;
use LdapRecord\LdapResultResponse;
use LdapRecord\Testing\LdapExpectation;
use LdapRecord\Testing\LdapFake;
use LdapRecord\Tests\TestCase;
use PHPUnit\Framework\Constraint\IsAnything;
use PHPUnit\Framework\Constraint\IsEqual;

class LdapFakeTest extends TestCase
{
    public function testOperation()
    {
        $fake = new LdapFake();

        $operation = $fake->operation('foo');

        $this->assertInstanceOf(LdapExpectation::class, $operation);
        $this->assertEquals('foo', $operation->getMethod());
    }

    public function testShouldAuthenticateWith()
    {
        $fake = new LdapFake();

        $fake->shouldAuthenticateWith('foo');

        $this->assertTrue($fake->hasExpectations('bind'));

        $expectation = $fake->getExpectations('bind')[0];

        $this->assertEquals('bind', $expectation->getMethod());
        $this->assertInstanceOf(IsEqual::class, $expectation->getExpectedArgs()[0]);
        $this->assertInstanceOf(IsAnything::class, $expectation->getExpectedArgs()[1]);
    }

    public function testExpect()
    {
        $fake = new LdapFake();

        $fake->expect(['foo' => 'bar']);
        $fake->expect($fake->operation('bar'));
        $fake->expect(new LdapExpectation('baz'));

        $this->assertTrue($fake->hasExpectations('foo'));
        $this->assertTrue($fake->hasExpectations('bar'));
        $this->assertTrue($fake->hasExpectations('baz'));
    }

    public function testRemoveExpectation()
    {
        $fake = new LdapFake();

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

    public function testShouldReturnErrorNumber()
    {
        $fake = new LdapFake();

        $fake->shouldReturnErrorNumber(10);

        $this->assertEquals(10, $fake->errNo());
    }

    public function testShouldReturnError()
    {
        $fake = new LdapFake();

        $fake->shouldReturnError('foo');

        $this->assertEquals('foo', $fake->getLastError());
    }

    public function testShouldReturnDiagnosticMessage()
    {
        $fake = new LdapFake();

        $fake->shouldReturnDiagnosticMessage('foo');

        $this->assertEquals('foo', $fake->getDiagnosticMessage());
    }

    public function testConnect()
    {
        $fake = new LdapFake();

        $this->assertTrue($fake->connect('host', 389));

        $fake = new LdapFake();

        $fake->expect(['connect' => false]);

        $this->assertFalse($fake->connect('host', 389));
    }

    public function testBindWithoutExpectationThrowsException()
    {
        $fake = new LdapFake();

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('LDAP method [bind] was unexpected.');

        $fake->bind('foo', 'bar');
    }

    public function testBindWithExpectationReturnsResult()
    {
        $fake = new LdapFake();

        $fake->expect(['bind' => $response = new LdapResultResponse()]);

        $this->assertSame($response, $fake->bind('foo', 'bar'));

        $this->assertTrue($fake->isBound());
    }

    public function testBindWithExpectationReturnsFailedResult()
    {
        $fake = new LdapFake();

        $fake->expect(['bind' => $response = new LdapResultResponse(1)]);

        $this->assertSame($response, $fake->bind('foo', 'bar'));

        $this->assertFalse($fake->isBound());
    }

    public function testClose()
    {
        $fake = new LdapFake();

        $this->assertTrue($fake->close());

        $fake = new LdapFake();

        $fake->expect(['close' => false]);

        $this->assertFalse($fake->close());
    }

    public function testGetEntries()
    {
        $this->assertEquals(['foo', 'bar'], (new LdapFake())->getEntries(['foo', 'bar']));
    }
}
