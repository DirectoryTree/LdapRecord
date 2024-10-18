<?php

namespace LdapRecord\Tests\Unit\Query\Model;

use LdapRecord\Connection;
use LdapRecord\Models\ActiveDirectory\Entry;
use LdapRecord\Query\Model\ActiveDirectoryBuilder;
use LdapRecord\Testing\LdapFake;
use LdapRecord\Tests\TestCase;

class ActiveDirectoryTest extends TestCase
{
    protected function newBuilder(): ActiveDirectoryBuilder
    {
        return (new ActiveDirectoryBuilder(new Connection([], new LdapFake)))->setModel(new Entry);
    }

    public function test_where_member_of()
    {
        $b = $this->newBuilder();

        $b->whereMemberOf('cn=Accounting,dc=org,dc=acme', $nested = false);

        $where = $b->filters['and'][0];

        $this->assertEquals('memberof', $where['field']);
        $this->assertEquals('=', $where['operator']);
        $this->assertEquals('\63\6e\3d\41\63\63\6f\75\6e\74\69\6e\67\2c\64\63\3d\6f\72\67\2c\64\63\3d\61\63\6d\65', $where['value']);
        $this->assertEquals('(memberof=cn=Accounting,dc=org,dc=acme)', $b->getUnescapedQuery());
    }

    public function test_where_member_of_nested()
    {
        $b = $this->newBuilder();

        $b->whereMemberOf('cn=Accounting,dc=org,dc=acme', $nested = true);

        $where = $b->filters['and'][0];

        $this->assertEquals('memberof:1.2.840.113556.1.4.1941:', $where['field']);
        $this->assertEquals('=', $where['operator']);
        $this->assertEquals('\63\6e\3d\41\63\63\6f\75\6e\74\69\6e\67\2c\64\63\3d\6f\72\67\2c\64\63\3d\61\63\6d\65', $where['value']);
        $this->assertEquals('(memberof:1.2.840.113556.1.4.1941:=cn=Accounting,dc=org,dc=acme)', $b->getUnescapedQuery());
    }

    public function test_or_where_member_of()
    {
        $b = $this->newBuilder();

        $b->orWhereEquals('cn', 'John Doe');
        $b->orWhereMemberOf('cn=Accounting,dc=org,dc=acme', $nested = false);

        $where = $b->filters['or'][1];

        $this->assertEquals('memberof', $where['field']);
        $this->assertEquals('=', $where['operator']);
        $this->assertEquals('\63\6e\3d\41\63\63\6f\75\6e\74\69\6e\67\2c\64\63\3d\6f\72\67\2c\64\63\3d\61\63\6d\65', $where['value']);
        $this->assertEquals(
            '(|(cn=John Doe)(memberof=cn=Accounting,dc=org,dc=acme))',
            $b->getUnescapedQuery()
        );
    }

    public function test_or_where_member_of_nested()
    {
        $b = $this->newBuilder();

        $b->orWhereEquals('cn', 'John Doe');
        $b->orWhereMemberOf('cn=Accounting,dc=org,dc=acme', $nested = true);

        $where = $b->filters['or'][1];

        $this->assertEquals('memberof:1.2.840.113556.1.4.1941:', $where['field']);
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

        $this->assertEquals('(!(UserAccountControl:1.2.840.113556.1.4.803:=2))', $b->getQuery());
    }

    public function test_built_where_disabled()
    {
        $b = $this->newBuilder();

        $b->whereDisabled();

        $this->assertEquals('(UserAccountControl:1.2.840.113556.1.4.803:=2)', $b->getQuery());
    }
}
