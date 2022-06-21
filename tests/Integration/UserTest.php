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

        $this->ou = OrganizationalUnit::query()->where('ou', 'User Test OU')->firstOr(function () {
            return OrganizationalUnit::create(['ou' => 'User Test OU']);
        });

        $this->ou->deleteLeafNodes();
    }

    protected function tearDown(): void
    {
        $this->ou->delete(true);

        Container::reset();

        parent::tearDown();
    }

    public function test_it_can_be_created_with_password()
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

        $this->assertEquals('cn=Foo,ou=User Test OU,dc=local,dc=com', $user->getDn());

        $this->assertEmpty($user->getObjectGuid());
        $this->assertEmpty($user->getModifications());

        $user->refresh();

        $this->assertNotEmpty($user->getObjectGuid());
        $this->assertTrue(Guid::isValid($user->getObjectGuid()));
    }
}
