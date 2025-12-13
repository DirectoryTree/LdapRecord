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
}
