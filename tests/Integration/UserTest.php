<?php

namespace LdapRecord\Tests\Integration;

use LdapRecord\Container;
use LdapRecord\Models\Attributes\Guid;
use LdapRecord\Models\OpenLDAP\OrganizationalUnit;
use LdapRecord\Tests\Integration\Fixtures\User;

class UserTest extends TestCase
{
    /** @var OrganizationalUnit */
    protected $ou;

    protected function setUp(): void
    {
        parent::setUp();

        Container::addConnection($this->makeConnection());

        $this->ou = OrganizationalUnit::query()->where('ou', 'Test OU')->firstOr(function () {
            return OrganizationalUnit::create(['ou' => 'Test OU']);
        });

        $this->ou->deleteLeafNodes();
    }

    protected function tearDown(): void
    {
        $this->ou->delete(true);

        Container::reset();

        parent::tearDown();
    }

    public function testUserCanBeCreatedWithPassword()
    {
        $user = (new User([
            'uid' => 'fbar',
            'cn' => 'Foo',
            'sn' => 'Bar',
            'givenName' => 'Foo',
            'uidNumber' => 1000,
            'gidNumber' => 1000,
            'password' => 'secret',
            'homeDirectory' => '/foo',
        ]))->inside($this->ou);

        $user->save();

        $this->assertEquals('cn=Foo,ou=Test OU,dc=local,dc=com', $user->getDn());

        $this->assertEmpty($user->getObjectGuid());
        $this->assertEmpty($user->getModifications());

        $user->refresh();

        $this->assertNotEmpty($user->getObjectGuid());
        $this->assertTrue(Guid::isValid($user->getObjectGuid()));
    }
}
