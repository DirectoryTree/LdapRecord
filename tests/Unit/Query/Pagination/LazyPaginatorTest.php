<?php

namespace LdapRecord\Tests\Unit\Query\Pagination;

use LdapRecord\Query\Builder;
use LdapRecord\Testing\ConnectionFake;
use LdapRecord\Testing\LdapFake;
use LdapRecord\Tests\TestCase;
use Mockery;
use RuntimeException;

class LazyPaginatorTest extends TestCase
{
    public function test_stopping_chunking_clears_paging_controls_before_reusing_the_query()
    {
        $serverControls = [];
        $ldap = Mockery::mock(LdapFake::class)->makePartial();
        $ldap->shouldReceive('setOption')->andReturnUsing(function ($option, $value) use (&$serverControls) {
            if ($option === LDAP_OPT_SERVER_CONTROLS) {
                $serverControls = $value;
            }

            return true;
        });

        $connection = (new ConnectionFake([], $ldap))->shouldBeConnected();
        $query = (new Builder($connection))->addControl(LDAP_CONTROL_SORTREQUEST, false, [['attr' => 'cn']]);
        $controls = $query->controls;
        $entries = [['dn' => 'cn=John,dc=local,dc=com']];

        $ldap->expect([
            LdapFake::operation('search')->once()->andReturn($entries),
            LdapFake::operation('search')->once()->andReturn($entries),
            LdapFake::operation('parseResult')->once()->andReturnResponse(controls: [
                LDAP_CONTROL_PAGEDRESULTS => ['value' => ['cookie' => 'next-page']],
            ]),
            LdapFake::operation('parseResult')->times(2)->andReturnResponse(),
        ]);

        $this->assertFalse($query->chunk(1, fn () => false));
        $this->assertSame($controls, $query->controls);
        $this->assertSame($controls, $serverControls);
        $this->assertSame($entries, $query->get());
        $this->assertSame($controls, $serverControls);

        $connection->tearDown();
    }

    public function test_callback_exceptions_clear_paging_controls()
    {
        $serverControls = [];
        $ldap = Mockery::mock(LdapFake::class)->makePartial();
        $ldap->shouldReceive('setOption')->andReturnUsing(function ($option, $value) use (&$serverControls) {
            if ($option === LDAP_OPT_SERVER_CONTROLS) {
                $serverControls = $value;
            }

            return true;
        });

        $connection = (new ConnectionFake([], $ldap))->shouldBeConnected();
        $query = (new Builder($connection))->addControl(LDAP_CONTROL_SORTREQUEST, false, [['attr' => 'cn']]);
        $controls = $query->controls;
        $exception = new RuntimeException('The callback failed.');

        $ldap->expect([
            LdapFake::operation('search')->once()->andReturn([['dn' => 'cn=John,dc=local,dc=com']]),
            LdapFake::operation('parseResult')->once()->andReturnResponse(controls: [
                LDAP_CONTROL_PAGEDRESULTS => ['value' => ['cookie' => 'next-page']],
            ]),
            LdapFake::operation('parseResult')->once()->andReturnResponse(),
        ]);

        try {
            $query->chunk(1, function () use ($exception) {
                throw $exception;
            });

            $this->fail('The callback exception was not thrown.');
        } catch (RuntimeException $e) {
            $this->assertSame($exception, $e);
        }

        $this->assertSame($controls, $query->controls);
        $this->assertSame($controls, $serverControls);

        $connection->tearDown();
    }

    public function test_parsing_exceptions_clear_paging_controls()
    {
        $serverControls = [];
        $ldap = Mockery::mock(LdapFake::class)->makePartial();
        $ldap->shouldReceive('setOption')->andReturnUsing(function ($option, $value) use (&$serverControls) {
            if ($option === LDAP_OPT_SERVER_CONTROLS) {
                $serverControls = $value;
            }

            return true;
        });

        $connection = (new ConnectionFake([], $ldap))->shouldBeConnected();
        $query = (new Builder($connection))->addControl(LDAP_CONTROL_SORTREQUEST, false, [['attr' => 'cn']]);
        $controls = $query->controls;
        $exception = new RuntimeException('Parsing failed.');

        $ldap->expect([
            LdapFake::operation('search')->once()->andReturn([['dn' => 'cn=John,dc=local,dc=com']]),
            LdapFake::operation('parseResult')->once()->andReturnResponse(controls: [
                LDAP_CONTROL_PAGEDRESULTS => ['value' => ['cookie' => 'next-page']],
            ]),
            LdapFake::operation('parseResult')->once()->andThrow($exception),
        ]);

        try {
            $query->chunk(1, fn () => null);

            $this->fail('The parsing exception was not thrown.');
        } catch (RuntimeException $e) {
            $this->assertSame($exception, $e);
        }

        $this->assertSame($controls, $query->controls);
        $this->assertSame($controls, $serverControls);

        $connection->tearDown();
    }

    public function test_completing_chunking_clears_paging_controls()
    {
        $serverControls = [];
        $ldap = Mockery::mock(LdapFake::class)->makePartial();
        $ldap->shouldReceive('setOption')->andReturnUsing(function ($option, $value) use (&$serverControls) {
            if ($option === LDAP_OPT_SERVER_CONTROLS) {
                $serverControls = $value;
            }

            return true;
        });

        $connection = (new ConnectionFake([], $ldap))->shouldBeConnected();
        $query = new Builder($connection);

        $ldap->expect([
            LdapFake::operation('search')->once()->andReturn([['dn' => 'cn=John,dc=local,dc=com']]),
            LdapFake::operation('parseResult')->once()->andReturnResponse(controls: [
                LDAP_CONTROL_PAGEDRESULTS => ['value' => ['cookie' => '']],
            ]),
            LdapFake::operation('parseResult')->once()->andReturnResponse(),
        ]);

        $this->assertTrue($query->chunk(1, fn () => null));
        $this->assertSame([], $query->controls);
        $this->assertSame([], $serverControls);

        $connection->tearDown();
    }
}
