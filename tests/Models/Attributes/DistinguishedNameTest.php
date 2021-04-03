<?php

namespace LdapRecord\Tests\Models\Attributes;

use LdapRecord\Models\Attributes\DistinguishedName;
use LdapRecord\Tests\TestCase;

class DistinguishedNameTest extends TestCase
{
    public function test_value_can_be_set()
    {
        $dn = new DistinguishedName('foo');
        $this->assertEquals('foo', $dn->get());

        $dn->set('bar');
        $this->assertEquals('bar', $dn->get());

        $this->assertEmpty((new DistinguishedName(null))->get());
    }

    public function test_components_returns_attributes()
    {
        $dn = new DistinguishedName(null);
        $this->assertEquals([], $dn->components());

        $dn = new DistinguishedName('foo');
        $this->assertEquals([], $dn->components());

        $dn = new DistinguishedName('cn=foo');
        $this->assertEquals(['cn=foo'], $dn->components());

        $dn = new DistinguishedName('cn=foo,dc=bar');
        $this->assertEquals(['cn=foo', 'dc=bar'], $dn->components());
    }

    public function test_values_excludes_attributes()
    {
        $dn = new DistinguishedName(null);
        $this->assertEquals([], $dn->values());

        $dn = new DistinguishedName('foo');
        $this->assertEquals([], $dn->values());

        $dn = new DistinguishedName('cn=foo');
        $this->assertEquals(['foo'], $dn->values());

        $dn = new DistinguishedName('cn=foo,dc=bar');
        $this->assertEquals(['foo', 'bar'], $dn->values());
    }

    public function test_is_ancestor_of()
    {
        $dn = new DistinguishedName('ou=foo,dc=bar,dc=baz');
        $this->assertFalse($dn->isAncestorOf(new DistinguishedName('ou=foo,dc=bar,dc=baz')));

        $dn = new DistinguishedName(null);
        $this->assertFalse($dn->isAncestorOf(new DistinguishedName(null)));

        $dn = new DistinguishedName('');
        $this->assertFalse($dn->isAncestorOf(new DistinguishedName('')));

        $dn = new DistinguishedName('cn=foo');
        $this->assertFalse($dn->isAncestorOf(new DistinguishedName('cn=bar')));

        $dn = new DistinguishedName('dc=bar');
        $this->assertTrue($dn->isAncestorOf(new DistinguishedName('cn=foo,dc=bar')));

        $dn = new DistinguishedName('ou=foo,dc=bar,dc=baz');
        $this->assertFalse($dn->isAncestorOf(new DistinguishedName('dc=bar,dc=baz')));
        $this->assertFalse($dn->isAncestorOf(new DistinguishedName('cn=John Doe,ou=foo,dc=bar,dc=zal')));
        $this->assertFalse($dn->isAncestorOf(new DistinguishedName('ou=foo,dc=bar,dc=baz')));
        $this->assertTrue($dn->isAncestorOf(new DistinguishedName('cn=John Doe,ou=foo,dc=bar,dc=baz')));
        $this->assertTrue($dn->isAncestorOf(new DistinguishedName('cn=John Doe,ou=foo,DC=BAR,DC=BAZ')));
        $this->assertTrue($dn->isAncestorOf(new DistinguishedName('cn=John Doe,ou=zal,ou=foo,DC=BAR,DC=BAZ')));
    }

    public function test_is_descendant_of()
    {
        $dn = new DistinguishedName(null);
        $this->assertFalse($dn->isDescendantOf(new DistinguishedName(null)));

        $dn = new DistinguishedName('');
        $this->assertFalse($dn->isDescendantOf(new DistinguishedName('')));

        $dn = new DistinguishedName('dc=bar');
        $this->assertFalse($dn->isDescendantOf(new DistinguishedName('dc=foo,dc=bar')));

        $dn = new DistinguishedName('dc=foo,dc=bar');
        $this->assertTrue($dn->isDescendantOf(new DistinguishedName('dc=bar')));

        $dn = new DistinguishedName('cn=John Doe,ou=foo,dc=bar,dc=baz');
        $this->assertFalse($dn->isDescendantOf(new DistinguishedName('dc=bar')));
        $this->assertFalse($dn->isDescendantOf(new DistinguishedName('dc=foo,dc=baz')));
        $this->assertFalse($dn->isDescendantOf(new DistinguishedName('cn=John Doe,ou=foo,dc=bar,dc=baz')));
        $this->assertTrue($dn->isDescendantOf(new DistinguishedName('ou=foo,dc=bar,dc=baz')));
        $this->assertTrue($dn->isDescendantOf(new DistinguishedName('ou=foo,DC=BAR,DC=BAZ')));
        $this->assertTrue($dn->isDescendantOf(new DistinguishedName('ou=foo,DC=BAR,DC=BAZ')));
    }

    public function test_is_child_of()
    {
        $dn = new DistinguishedName(null);
        $this->assertFalse($dn->isChildOf(new DistinguishedName(null)));

        $dn = new DistinguishedName('cn=bar');
        $this->assertFalse($dn->isParentOf(new DistinguishedName('ou=foo')));

        $dn = new DistinguishedName('dc=bar');
        $this->assertFalse($dn->isChildOf(new DistinguishedName('ou=foo,dc=bar')));

        $dn = new DistinguishedName('cn=John Doe,ou=foo,dc=bar,dc=baz');
        $this->assertTrue($dn->isChildOf(new DistinguishedName('ou=foo,dc=bar,dc=baz')));
    }

    public function test_is_parent_of()
    {
        $dn = new DistinguishedName(null);
        $this->assertFalse($dn->isParentOf(new DistinguishedName(null)));

        $dn = new DistinguishedName('ou=foo');
        $this->assertFalse($dn->isParentOf(new DistinguishedName('cn=bar')));

        $dn = new DistinguishedName('ou=foo,dc=bar');
        $this->assertFalse($dn->isParentOf(new DistinguishedName('dc=bar')));

        $dn = new DistinguishedName('ou=foo,dc=bar,dc=baz');
        $this->assertTrue($dn->isParentOf(new DistinguishedName('cn=John Doe,ou=foo,dc=bar,dc=baz')));
    }

    public function test_parent()
    {
        $dn = new DistinguishedName(null);
        $this->assertNull($dn->parent());

        $dn = new DistinguishedName('');
        $this->assertNull($dn->parent());

        $dn = new DistinguishedName('invalid');
        $this->assertNull($dn->parent());

        $dn = new DistinguishedName('cn=John');
        $this->assertNull($dn->parent());

        $dn = new DistinguishedName('cn=John,ou=foo');
        $this->assertEquals('ou=foo', $dn->parent());

        $dn = new DistinguishedName('cn=John Doe,ou=foo,dc=bar,dc=baz');
        $this->assertEquals('ou=foo,dc=bar,dc=baz', $dn->parent());
    }

    public function test_relative()
    {
        $dn = new DistinguishedName(null);
        $this->assertNull($dn->relative());

        $dn = new DistinguishedName('');
        $this->assertNull($dn->relative());

        $dn = new DistinguishedName('invalid');
        $this->assertNull($dn->relative());

        $dn = new DistinguishedName('cn=John');
        $this->assertEquals('cn=John', $dn->relative());

        $dn = new DistinguishedName('cn=John,ou=foo');
        $this->assertEquals('cn=John', $dn->relative());

        $dn = new DistinguishedName('cn=John Doe,ou=foo,dc=bar,dc=baz');
        $this->assertEquals('cn=John Doe', $dn->relative());
    }

    public function test_name()
    {
        $dn = new DistinguishedName(null);
        $this->assertNull($dn->name());

        $dn = new DistinguishedName('');
        $this->assertNull($dn->name());

        $dn = new DistinguishedName('invalid');
        $this->assertNull($dn->name());

        $dn = new DistinguishedName('cn=John');
        $this->assertEquals('John', $dn->name());

        $dn = new DistinguishedName('cn=John,ou=foo');
        $this->assertEquals('John', $dn->name());

        $dn = new DistinguishedName('cn=John Doe,ou=foo,dc=bar,dc=baz');
        $this->assertEquals('John Doe', $dn->name());
    }

    public function test_assoc()
    {
        $dn = new DistinguishedName('foo=bar,baz=zal,bar=baz');

        $this->assertEquals(
            ['foo' => ['bar'], 'baz' => ['zal'], 'bar' => ['baz']],
            $dn->assoc()
        );

        $dn = new DistinguishedName('foo=bar,foo=bar,foo=bar');

        $this->assertEquals(
            ['foo' => ['bar', 'bar', 'bar']],
            $dn->assoc()
        );
    }

    public function test_assoc_with_empty_dn()
    {
        $dn = new DistinguishedName(null);

        $this->assertEmpty($dn->assoc());
        $this->assertIsArray($dn->assoc());
    }

    public function test_assoc_with_malformed_dn()
    {
        $dn = new DistinguishedName('foo=bar,fooar,foo=bar');

        $this->assertEmpty($dn->assoc());
        $this->assertIsArray($dn->assoc());

        $dn = new DistinguishedName('foo=bar');

        $this->assertEquals(
           ['foo' => ['bar']],
           $dn->assoc()
       );
    }

    public function test_assoc_with_alternate_casing()
    {
        $dn = new DistinguishedName('cn=foo,dc=bar,DC=baz');

        $this->assertEquals([
            'cn' => ['foo'],
            'dc' => [
                'bar',
                'baz',
            ],
        ], $dn->assoc());
    }
}
