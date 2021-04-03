<?php

namespace LdapRecord\Tests\Models\Attributes;

use LdapRecord\Models\Attributes\DistinguishedName;
use LdapRecord\Models\Attributes\DistinguishedNameBuilder;
use LdapRecord\Tests\TestCase;

class DistinguishedNameBuilderTest extends TestCase
{
    public function testGet()
    {
        $builder = new DistinguishedNameBuilder('cn=john,dc=local,dc=com');

        $this->assertEquals('cn=john,dc=local,dc=com', $builder->get());
    }

    public function testReverse()
    {
        $builder = new DistinguishedNameBuilder('cn=john,dc=local,dc=com');

        $this->assertEquals('dc=com,dc=local,cn=john', $builder->reverse()->get());
    }

    public function testShift()
    {
        $this->assertEquals(
            'dc=local,dc=com',
            (new DistinguishedNameBuilder('cn=john,dc=local,dc=com'))->shift()->get()
        );

        $this->assertEquals(
            'dc=local,dc=com',
            (new DistinguishedNameBuilder('cn=john,dc=local,dc=com'))->shift(1, $removed)->get()
        );
        $this->assertEquals(['cn=john'], $removed);

        $this->assertEquals(
            'dc=com',
            (new DistinguishedNameBuilder('cn=john,dc=local,dc=com'))->shift(2, $removed)->get()
        );
        $this->assertEquals(['cn=john', 'dc=local'], $removed);

        $this->assertEquals(
            '',
            (new DistinguishedNameBuilder('cn=john,dc=local,dc=com'))->shift(3, $removed)->get()
        );
        $this->assertEquals(['cn=john', 'dc=local', 'dc=com'], $removed);

        $this->assertEquals(
            '',
            (new DistinguishedNameBuilder('cn=john,dc=local,dc=com'))->shift(5, $removed)->get()
        );
        $this->assertEquals(['cn=john', 'dc=local', 'dc=com'], $removed);
    }

    public function testPop()
    {
        $this->assertEquals(
            'cn=john,dc=local',
            (new DistinguishedNameBuilder('cn=john,dc=local,dc=com'))->pop()->get()
        );

        $this->assertEquals(
            'cn=john,dc=local',
            (new DistinguishedNameBuilder('cn=john,dc=local,dc=com'))->pop(1, $removed)->get()
        );
        $this->assertEquals(['dc=com'], $removed);

        $this->assertEquals(
            'cn=john',
            (new DistinguishedNameBuilder('cn=john,dc=local,dc=com'))->pop(2, $removed)->get()
        );
        $this->assertEquals(['dc=local', 'dc=com'], $removed);

        $this->assertEquals(
            '',
            (new DistinguishedNameBuilder('cn=john,dc=local,dc=com'))->pop(3, $removed)->get()
        );
        $this->assertEquals(['cn=john', 'dc=local', 'dc=com'], $removed);

        $this->assertEquals(
            '',
            (new DistinguishedNameBuilder('cn=john,dc=local,dc=com'))->pop(5, $removed)->get()
        );
        $this->assertEquals(['cn=john', 'dc=local', 'dc=com'], $removed);
    }

    public function testAppend()
    {
        $this->assertEquals(
            'cn=john,dc=local,dc=com,dc=org',
            (new DistinguishedNameBuilder('cn=john,dc=local,dc=com'))->append('dc', 'org')->get()
        );

        $this->assertEquals(
            'cn=john,dc=local,dc=com,dc=org',
            (new DistinguishedNameBuilder('cn=john,dc=local,dc=com'))->append('dc=org')->get()
        );

        $this->assertEquals(
            'cn=john,dc=local,dc=com,dc=org',
            (new DistinguishedNameBuilder('cn=john,dc=local,dc=com'))->append(' dc ', ' org ')->get()
        );

        $this->assertEquals(
            'cn=john,dc=local,dc=com,dc=\2c',
            (new DistinguishedNameBuilder('cn=john,dc=local,dc=com'))->append('dc', ',')->get()
        );
    }

    public function testPrepend()
    {
        $this->assertEquals(
            'ou=users,cn=john,dc=local,dc=com',
            (new DistinguishedNameBuilder('cn=john,dc=local,dc=com'))->prepend('ou', 'users')->get()
        );

        $this->assertEquals(
            'ou=users,cn=john,dc=local,dc=com',
            (new DistinguishedNameBuilder('cn=john,dc=local,dc=com'))->prepend('ou=users')->get()
        );

        $this->assertEquals(
            'ou=users,cn=john,dc=local,dc=com',
            (new DistinguishedNameBuilder('cn=john,dc=local,dc=com'))->prepend(' ou ', ' users ')->get()
        );

        $this->assertEquals(
            'ou=\2c,cn=john,dc=local,dc=com',
            (new DistinguishedNameBuilder('cn=john,dc=local,dc=com'))->prepend('ou', ',')->get()
        );
    }

    public function testChaining()
    {
        $dn = DistinguishedName::of();

        $dn
            ->append('cn', 'John Doe')
            ->append('dc', 'local')
            ->append('dc', 'com');

        $this->assertInstanceOf(DistinguishedName::class, $dn->get());
        $this->assertEquals('cn=John Doe,dc=local,dc=com', (string) $dn->get());
    }

    public function testChainingWithShiftPrependPopAppend()
    {
        $dn = DistinguishedName::of('cn=John Doe,dc=local,dc=com')
            ->shift(1, $removed)
            ->prepend('ou', 'users')
            ->prepend($removed)
            ->pop(1, $removed)
            ->append('dc', 'org')
            ->append($removed)
            ->get();

        $this->assertEquals('cn=John Doe,ou=users,dc=local,dc=org,dc=com', (string) $dn);
    }
}
