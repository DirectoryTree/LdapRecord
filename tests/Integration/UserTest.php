<?php

namespace LdapRecord\Tests\Integration;

use LdapRecord\Container;
use LdapRecord\DetailedError;
use LdapRecord\LdapRecordException;
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

        Container::flush();

        parent::tearDown();
    }

    protected function createUser(array $attributes = [])
    {
        $user = (new User)
            ->inside($this->ou)
            ->fill(array_merge([
                'uid' => 'fbar',
                'cn' => 'Foo',
                'sn' => 'Bar',
                'givenName' => 'Foo',
                'uidNumber' => 1000,
                'gidNumber' => 1000,
                'homeDirectory' => '/foo',
            ], $attributes));

        $user->save();

        return $user;
    }

    public function test_it_can_be_created()
    {
        $user = $this->createUser();

        $this->assertEquals('cn=Foo,ou=User Test OU,dc=local,dc=com', $user->getDn());

        $this->assertEmpty($user->getObjectGuid());
        $this->assertEmpty($user->getModifications());

        $user->refresh();

        $this->assertNotEmpty($user->getObjectGuid());
        $this->assertTrue(Guid::isValid($user->getObjectGuid()));
    }

    public function test_it_can_set_password()
    {
        $user = $this->createUser();

        $user->fill(['password' => 'secret'])->save();

        $user->refresh();

        $conn = $this->makeConnection([
            'username' => $user->getDn(),
            'password' => 'secret',
        ]);

        $conn->connect();

        $this->assertTrue($conn->isConnected());

        $conn->disconnect();
    }

    public function test_it_can_change_password()
    {
        $user = $this->createUser();

        $user->fill(['password' => 'secret'])->save();

        $user->refresh();

        $user->fill(['password' => ['secret', 'super-secret']])->save();

        $conn = $this->makeConnection([
            'username' => $user->getDn(),
            'password' => 'super-secret',
        ]);

        $conn->connect();

        $this->assertTrue($conn->isConnected());
        $this->assertFalse($conn->auth()->attempt($user->getDn(), 'secret'));

        $conn->disconnect();
    }

    public function test_it_can_bind_and_then_change_password()
    {
        $user = $this->createUser();

        $user->fill(['password' => 'secret'])->save();

        $user->refresh();

        $conn = $user->resolveConnection();

        // Bind as the user to perform the password change underneath themselves.
        $conn->auth()->bind($user->getDn(), 'secret');

        $user->fill(['password' => ['secret', 'super-secret']])->save();

        $conn->disconnect();

        $conn->auth()->bind($user->getDn(), 'super-secret');

        $this->assertTrue($conn->isConnected());
        $this->assertFalse($conn->auth()->attempt($user->getDn(), 'secret'));

        $conn->disconnect();
    }

    public function test_it_throws_exception_when_providing_an_invalid_password_during_change()
    {
        $user = $this->createUser();

        $user->fill(['password' => 'secret'])->save();

        $user->refresh();

        try {
            $user->fill(['password' => ['invalid', 'super-secret']])->save();
        } catch (LdapRecordException $e) {
            $this->assertEquals('ldap_modify_batch(): Batch Modify: No such attribute', $e->getMessage());

            $this->assertInstanceOf(DetailedError::class, $error = $e->getDetailedError());

            $this->assertEquals(16, $error->getErrorCode());
            $this->assertEquals('No such attribute', $error->getErrorMessage());
            $this->assertEquals('modify/delete: userPassword: no such value', $error->getDiagnosticMessage());
        }
    }
}
