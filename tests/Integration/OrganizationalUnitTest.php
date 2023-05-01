<?php

namespace LdapRecord\Tests\Integration;

use LdapRecord\Container;
use LdapRecord\Models\OpenLDAP\OrganizationalUnit;
use LdapRecord\Tests\Integration\Concerns\SetupTestConnection;

class OrganizationalUnitTest extends TestCase
{
    use SetupTestConnection;

    protected function setUp(): void
    {
        parent::setUp();

        OrganizationalUnit::destroy(OrganizationalUnit::all(), $recursive = true);
    }

    protected function tearDown(): void
    {
        Container::flush();

        parent::tearDown();
    }

    public function test_it_can_be_created()
    {
        $ou = OrganizationalUnit::create(['ou' => 'foo']);

        $this->assertTrue($ou->exists);
        $this->assertTrue($ou->wasRecentlyCreated);

        $this->assertCount(1, OrganizationalUnit::all());
    }

    public function test_it_can_be_created_inside_another_ou()
    {
        $foo = OrganizationalUnit::create(['ou' => 'foo']);

        $bar = (new OrganizationalUnit(['ou' => 'bar']))->inside($foo);

        $bar->save();

        $this->assertEquals('ou=foo,dc=local,dc=com', $foo->getDn());
        $this->assertEquals('ou=bar,ou=foo,dc=local,dc=com', $bar->getDn());

        $this->assertCount(2, OrganizationalUnit::all());

        $this->assertTrue($bar->isChildOf($foo));
        $this->assertTrue($bar->isDescendantOf($foo));

        $this->assertTrue($foo->isParentOf($bar));
        $this->assertTrue($foo->isAncestorOf($bar));
    }

    public function test_it_throws_exception_during_save_when_invalid_dn_syntax_is_given()
    {
        $foo = OrganizationalUnit::make()->setDn('ou,invalid,dc=invalid');

        $this->expectExceptionMessage('Invalid DN syntax');

        $foo->save();
    }

    public function test_it_can_be_renamed()
    {
        $ou = OrganizationalUnit::create(['ou' => 'foo']);

        $this->assertTrue($ou->exists);

        $ou->rename('bar');

        $this->assertEquals('ou=bar,dc=local,dc=com', $ou->getDn());

        $this->assertTrue($ou->is(OrganizationalUnit::find('ou=bar,dc=local,dc=com')));
    }

    public function test_it_can_be_renamed_with_rdn()
    {
        $ou = OrganizationalUnit::create(['ou' => 'foo']);

        $this->assertTrue($ou->exists);

        $ou->rename('ou=bar');

        $this->assertEquals('ou=bar,dc=local,dc=com', $ou->getDn());

        $this->assertTrue($ou->is(OrganizationalUnit::find('ou=bar,dc=local,dc=com')));
    }

    public function test_it_can_be_deleted()
    {
        $ou = OrganizationalUnit::create(['ou' => 'foo']);

        $this->assertCount(1, OrganizationalUnit::all());

        $ou->delete();

        $this->assertCount(0, OrganizationalUnit::all());
    }

    public function test_it_throws_exception_when_deleting_non_leaf_node()
    {
        $foo = OrganizationalUnit::create(['ou' => 'foo']);

        $bar = (new OrganizationalUnit(['ou' => 'bar']))->inside($foo);

        $bar->save();

        $this->expectExceptionMessage('Operation not allowed on non-leaf');

        $foo->delete();
    }

    public function test_it_can_delete_non_leaf_node_with_recursive_delete_option()
    {
        $foo = OrganizationalUnit::create(['ou' => 'foo']);

        $bar = (new OrganizationalUnit(['ou' => 'bar']))->inside($foo);

        $bar->save();

        $foo->delete($recursive = true);

        $this->assertCount(0, OrganizationalUnit::all());
    }
}
