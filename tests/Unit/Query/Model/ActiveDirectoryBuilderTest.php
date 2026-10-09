<?php

namespace LdapRecord\Tests\Unit\Query\Model;

use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\LdapInterface;
use LdapRecord\Models\ActiveDirectory\Entry;
use LdapRecord\Models\Attributes\SecurityDescriptor;
use LdapRecord\Query\Builder;
use LdapRecord\Query\Model\ActiveDirectoryBuilder;
use LdapRecord\Testing\LdapFake;
use LdapRecord\Tests\TestCase;

class ActiveDirectoryBuilderTest extends TestCase
{
    public function test_security_descriptors_are_selected_with_a_ber_encoded_control()
    {
        $connection = new Connection([], new LdapFake);
        $builder = new ActiveDirectoryBuilder(new Entry, new Builder($connection));

        $builder->select(['cn', 'mail'])->withSecurityDescriptor();

        $this->assertSame(['objectguid', 'cn', 'mail', 'ntsecuritydescriptor', 'objectclass'], $builder->getSelects());
        $this->assertSame([
            LdapInterface::OID_SERVER_SD_FLAGS => [
                'oid' => LdapInterface::OID_SERVER_SD_FLAGS,
                'isCritical' => true,
                'value' => hex2bin('3003020107'),
            ],
        ], $builder->toBase()->controls);

        $builder->withSecurityDescriptor(SecurityDescriptor::DACL_SECURITY_INFORMATION);

        $this->assertSame(hex2bin('3003020104'), $builder->toBase()->controls[LdapInterface::OID_SERVER_SD_FLAGS]['value']);
        $this->assertCount(1, $builder->toBase()->controls);
    }

    public function test_security_descriptor_control_is_sent_during_a_read()
    {
        $controls = [LdapInterface::OID_SERVER_SD_FLAGS => [
            'oid' => LdapInterface::OID_SERVER_SD_FLAGS,
            'isCritical' => true,
            'value' => hex2bin('3003020107'),
        ]];
        $ldap = (new LdapFake)->expect([
            'isBound' => true,
            LdapFake::operation('setOption')->once()->with(LDAP_OPT_SERVER_CONTROLS, $controls)->andReturnTrue(),
            LdapFake::operation('search')->once()->with('', '(objectclass=*)', ['objectguid', 'cn', 'ntsecuritydescriptor', 'objectclass'], false, 0)->andReturn([]),
        ]);
        $connection = new Connection([], $ldap);
        $builder = new ActiveDirectoryBuilder(new Entry, new Builder($connection));

        $builder->select('cn')->withSecurityDescriptor()->toBase()->run('(objectclass=*)');

        $ldap->assertMinimumExpectationCounts();
    }

    /** @dataProvider invalidSecurityDescriptorParts */
    public function test_invalid_security_descriptor_parts_are_rejected(int $parts)
    {
        $connection = new Connection([], new LdapFake);
        $builder = new ActiveDirectoryBuilder(new Entry, new Builder($connection));

        $this->expectException(\InvalidArgumentException::class);

        $builder->withSecurityDescriptor($parts);
    }

    public static function invalidSecurityDescriptorParts(): array
    {
        return [[0], [-1], [16], [0x8000]];
    }

    protected function newBuilder(): ActiveDirectoryBuilder
    {
        $connection = new Connection([], new LdapFake);

        Container::addConnection($connection);

        return new ActiveDirectoryBuilder(
            new Entry, new Builder($connection)
        );
    }

    public function test_where_member_of()
    {
        $b = $this->newBuilder();

        $b->whereMemberOf('cn=Accounting,dc=org,dc=acme');

        $this->assertEquals('(memberof=cn=Accounting,dc=org,dc=acme)', $b->getUnescapedQuery());
    }

    public function test_where_member_of_substitutes_base_dn()
    {
        $b = $this->newBuilder();
        $b->setBaseDn('dc=org,dc=acme');
        $b->whereMemberOf('cn=Accounting,{base}');

        $this->assertEquals(
            '(memberof=cn=Accounting,dc=org,dc=acme)',
            $b->getUnescapedQuery()
        );
    }

    public function test_where_member_of_nested()
    {
        $b = $this->newBuilder();

        $b->whereMemberOf('cn=Accounting,dc=org,dc=acme', nested: true);

        $this->assertEquals('(memberof:1.2.840.113556.1.4.1941:=cn=Accounting,dc=org,dc=acme)', $b->getUnescapedQuery());
    }

    public function test_where_member_of_nested_substitutes_base_dn()
    {
        $b = $this->newBuilder();
        $b->setBaseDn('dc=org,dc=acme');
        $b->whereMemberOf('cn=Accounting,{base}', nested: true);

        $this->assertEquals(
            '(memberof:1.2.840.113556.1.4.1941:=cn=Accounting,dc=org,dc=acme)',
            $b->getUnescapedQuery()
        );
    }

    public function test_or_where_member_of()
    {
        $b = $this->newBuilder();

        $b->orWhereEquals('cn', 'John Doe');
        $b->orWhereMemberOf('cn=Accounting,dc=org,dc=acme');

        $this->assertEquals(
            '(|(cn=John Doe)(memberof=cn=Accounting,dc=org,dc=acme))',
            $b->getUnescapedQuery()
        );
    }

    public function test_or_where_member_of_substitutes_base_dn()
    {
        $b = $this->newBuilder();
        $b->setBaseDn('dc=org,dc=acme');
        $b->orWhereEquals('cn', 'John Doe');
        $b->orWhereMemberOf('cn=Accounting,{base}');

        $this->assertEquals(
            '(|(cn=John Doe)(memberof=cn=Accounting,dc=org,dc=acme))',
            $b->getUnescapedQuery()
        );
    }

    public function test_or_where_member_of_nested()
    {
        $b = $this->newBuilder();

        $b->orWhereEquals('cn', 'John Doe');
        $b->orWhereMemberOf('cn=Accounting,dc=org,dc=acme', nested: true);

        $this->assertEquals(
            '(|(cn=John Doe)(memberof:1.2.840.113556.1.4.1941:=cn=Accounting,dc=org,dc=acme))',
            $b->getUnescapedQuery()
        );
    }

    public function test_built_where_enabled()
    {
        $b = $this->newBuilder();

        $b->whereEnabled();

        $this->assertEquals('(!(UserAccountControl:1.2.840.113556.1.4.803:=2))', $b->getQuery()->getQuery());
    }

    public function test_built_where_disabled()
    {
        $b = $this->newBuilder();

        $b->whereDisabled();

        $this->assertEquals('(UserAccountControl:1.2.840.113556.1.4.803:=2)', $b->getQuery()->getQuery());
    }

    public function test_select_with_variadic_arguments()
    {
        $b = $this->newBuilder();

        $selects = $b->select('cn', 'description')->getSelects();

        $this->assertContains('cn', $selects);
        $this->assertContains('description', $selects);
        $this->assertContains('objectguid', $selects); // GUID key always included
    }

    public function test_select_with_array_argument()
    {
        $b = $this->newBuilder();

        $selects = $b->select(['cn', 'description'])->getSelects();

        $this->assertContains('cn', $selects);
        $this->assertContains('description', $selects);
        $this->assertContains('objectguid', $selects);
    }

    public function test_select_with_empty_array_defaults_to_all()
    {
        $b = $this->newBuilder();

        $selects = $b->select([])->getSelects();

        $this->assertContains('*', $selects);
        $this->assertContains('objectguid', $selects);
    }

    public function test_add_select_with_variadic_arguments()
    {
        $b = $this->newBuilder();

        $selects = $b->select('cn')->addSelect('description', 'mail')->getSelects();

        $this->assertContains('cn', $selects);
        $this->assertContains('description', $selects);
        $this->assertContains('mail', $selects);
        $this->assertContains('objectguid', $selects);
    }

    public function test_or_filter_extracts_filters_from_nested_query()
    {
        $b = $this->newBuilder();

        $query = $b->orFilter(function ($query) {
            $query->whereEquals('foo', '1');
            $query->whereEquals('foo', '2');
        })->getUnescapedQuery();

        $this->assertEquals('(|(foo=1)(foo=2))', $query);
    }

    public function test_or_filter_preserves_nested_and_filter_when_followed_by_where()
    {
        $b = $this->newBuilder();

        $query = $b->orFilter(function ($query) {
            $query->andFilter(function ($query) {
                $query->whereStartsWith('givenName', 'John');
                $query->whereStartsWith('sn', 'Smith');
            });
            $query->where('mail', '=', 'John Smith');
        })->getUnescapedQuery();

        $this->assertEquals('(|(&(givenName=John*)(sn=Smith*))(mail=John Smith))', $query);
    }

    public function test_or_filter_preserves_nested_and_filter_when_preceded_by_where()
    {
        $b = $this->newBuilder();

        $query = $b->orFilter(function ($query) {
            $query->where('mail', '=', 'John Smith');
            $query->andFilter(function ($query) {
                $query->whereStartsWith('givenName', 'John');
                $query->whereStartsWith('sn', 'Smith');
            });
        })->getUnescapedQuery();

        $this->assertEquals('(|(mail=John Smith)(&(givenName=John*)(sn=Smith*)))', $query);
    }

    public function test_and_filter_extracts_filters_from_nested_query()
    {
        $b = $this->newBuilder();

        $query = $b->andFilter(function ($query) {
            $query->whereEquals('foo', '1');
            $query->whereEquals('foo', '2');
        })->getUnescapedQuery();

        $this->assertEquals('(&(foo=1)(foo=2))', $query);
    }
}
