<?php

namespace LdapRecord\Tests\Unit\Models\Attributes;

use LdapRecord\Models\Attributes\DistinguishedName;
use LdapRecord\Models\Attributes\DistinguishedNameBuilder;
use LdapRecord\Tests\TestCase;

class DistinguishedNameBuilderTest extends TestCase
{
    public function test_get()
    {
        $builder = new DistinguishedNameBuilder('cn=john,dc=local,dc=com');

        $this->assertEquals('cn=john,dc=local,dc=com', $builder->get());
    }

    public function test_reverse()
    {
        $builder = new DistinguishedNameBuilder('cn=john,dc=local,dc=com');

        $this->assertEquals('dc=com,dc=local,cn=john', $builder->reverse()->get());
    }

    public function test_shift()
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

    public function test_pop()
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

    public function test_append()
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

    public function test_prepend()
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

    public function test_components_arrays_and_strings_can_be_passed_to_append_and_prepend()
    {
        $dn = (new DistinguishedNameBuilder)
            ->prepend([
                'cn=John Doe',
                ['dc', 'local'],
                ['dc', 'com'],
            ])
            ->get();

        $this->assertEquals('cn=John Doe,dc=local,dc=com', $dn);

        $dn = (new DistinguishedNameBuilder)
            ->append([
                'cn=John Doe',
                ['dc', 'local'],
                ['dc', 'com'],
            ])
            ->get();

        $this->assertEquals('cn=John Doe,dc=local,dc=com', $dn);
    }

    public function test_chaining()
    {
        $dn = DistinguishedName::of();

        $dn
            ->append('cn', 'John Doe')
            ->append('dc', 'local')
            ->append('dc', 'com');

        $this->assertInstanceOf(DistinguishedName::class, $dn->get());
        $this->assertEquals('cn=John Doe,dc=local,dc=com', (string) $dn->get());
    }

    public function test_chaining_with_shift_prepend_pop_append()
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

    public function test_components()
    {
        $this->assertEmpty(
            DistinguishedName::of('')->components()
        );

        $this->assertEquals(
            [
                ['cn', 'John Doe'],
                ['dc', 'local'],
                ['dc', 'com'],
            ],
            DistinguishedName::of('cn=John Doe,dc=local,dc=com')->components()
        );
    }

    public function test_components_of_type()
    {
        $this->assertEmpty(
            DistinguishedName::of('cn=John Doe,dc=local,dc=com')->components('dn')
        );

        $this->assertEquals(
            [
                ['dc', 'local'],
                ['dc', 'com'],
            ],
            DistinguishedName::of('cn=John Doe,dc=local,dc=com')->components('dc')
        );
    }

    public function test_missing_method_calls_are_proxied_to_dn_instance()
    {
        $builder = DistinguishedName::of('cn=John Doe,dc=local,dc=com');

        $this->assertEquals([
            'John Doe',
            'local',
            'com',
        ], $builder->values());
    }
}
