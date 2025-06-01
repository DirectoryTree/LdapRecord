<?php

namespace LdapRecord\Tests\Unit\Query\Model;

use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\Models\ActiveDirectory\Entry;
use LdapRecord\Query\Builder;
use LdapRecord\Query\Model\ActiveDirectoryBuilder;
use LdapRecord\Testing\LdapFake;
use LdapRecord\Tests\TestCase;

class ActiveDirectoryBuilderTest extends TestCase
{
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

        $where = $b->getQuery()->filters['and'][0];

        $this->assertEquals('memberof', $where['attribute']);
        $this->assertEquals('=', $where['operator']);
        $this->assertEquals('\63\6e\3d\41\63\63\6f\75\6e\74\69\6e\67\2c\64\63\3d\6f\72\67\2c\64\63\3d\61\63\6d\65', $where['value']);
        $this->assertEquals('(memberof=cn=Accounting,dc=org,dc=acme)', $b->getUnescapedQuery());
    }

    public function test_where_member_of_substitutes_base_dn()
    {
        $b = $this->newBuilder();
        $b->setBaseDn('dc=org,dc=acme');
        $b->whereMemberOf('cn=Accounting,{base}');
        $where = $b->getQuery()->filters['and'][0];
        $this->assertEquals('memberof', $where['attribute']);
        $this->assertEquals('=', $where['operator']);
        $this->assertEquals(
            '\63\6e\3d\41\63\63\6f\75\6e\74\69\6e\67\2c\64\63\3d\6f\72\67\2c\64\63\3d\61\63\6d\65',
            $where['value']
        );
        $this->assertEquals(
            '(memberof=cn=Accounting,dc=org,dc=acme)',
            $b->getUnescapedQuery()
        );
    }

    public function test_where_member_of_nested()
    {
        $b = $this->newBuilder();

        $b->whereMemberOf('cn=Accounting,dc=org,dc=acme', nested: true);

        $where = $b->getQuery()->filters['and'][0];

        $this->assertEquals('memberof:1.2.840.113556.1.4.1941:', $where['attribute']);
        $this->assertEquals('=', $where['operator']);
        $this->assertEquals('\63\6e\3d\41\63\63\6f\75\6e\74\69\6e\67\2c\64\63\3d\6f\72\67\2c\64\63\3d\61\63\6d\65', $where['value']);
        $this->assertEquals('(memberof:1.2.840.113556.1.4.1941:=cn=Accounting,dc=org,dc=acme)', $b->getUnescapedQuery());
    }

    public function test_where_member_of_nested_substitutes_base_dn()
    {
        $b = $this->newBuilder();
        $b->setBaseDn('dc=org,dc=acme');
        $b->whereMemberOf('cn=Accounting,{base}', nested: true);
        $where = $b->getQuery()->filters['and'][0];
        $this->assertEquals('memberof:1.2.840.113556.1.4.1941:', $where['attribute']);
        $this->assertEquals('=', $where['operator']);
        $this->assertEquals(
            '\63\6e\3d\41\63\63\6f\75\6e\74\69\6e\67\2c\64\63\3d\6f\72\67\2c\64\63\3d\61\63\6d\65',
            $where['value']
        );
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

        $where = $b->getQuery()->filters['or'][1];

        $this->assertEquals('memberof', $where['attribute']);
        $this->assertEquals('=', $where['operator']);
        $this->assertEquals('\63\6e\3d\41\63\63\6f\75\6e\74\69\6e\67\2c\64\63\3d\6f\72\67\2c\64\63\3d\61\63\6d\65', $where['value']);
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
        $where = $b->getQuery()->filters['or'][1];
        $this->assertEquals('memberof', $where['attribute']);
        $this->assertEquals('=', $where['operator']);
        $this->assertEquals(
            '\63\6e\3d\41\63\63\6f\75\6e\74\69\6e\67\2c\64\63\3d\6f\72\67\2c\64\63\3d\61\63\6d\65',
            $where['value']
        );
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

        $where = $b->getQuery()->filters['or'][1];

        $this->assertEquals('memberof:1.2.840.113556.1.4.1941:', $where['attribute']);
        $this->assertEquals('=', $where['operator']);
        $this->assertEquals('\63\6e\3d\41\63\63\6f\75\6e\74\69\6e\67\2c\64\63\3d\6f\72\67\2c\64\63\3d\61\63\6d\65', $where['value']);
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
}
