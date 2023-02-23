<?php

namespace LdapRecord\Tests\Unit\Models\Attributes;

use LdapRecord\Models\Attributes\Password;
use LdapRecord\Tests\TestCase;

class PasswordTest extends TestCase
{
    public function test_encode()
    {
        $password = 'password';
        $encoded = Password::encode($password);

        $this->assertEquals(
            '2200700061007300730077006f00720064002200',
            bin2hex($encoded)
        );
    }

    // Salted Hash Tests. //

    public function test_ssha()
    {
        $password = Password::ssha('password');

        $this->assertNotEquals(
            $password,
            Password::ssha('password')
        );

        $this->assertEquals(
            $password,
            Password::ssha('password', Password::getSalt($password))
        );
    }

    public function test_ssha256()
    {
        $password = Password::ssha256('password');

        $this->assertNotEquals($password, Password::ssha256('password'));

        $this->assertEquals($password, Password::ssha256('password', Password::getSalt($password)));
    }

    public function test_ssha384()
    {
        $password = Password::ssha384('password');

        $this->assertNotEquals($password, Password::ssha384('password'));
        $this->assertEquals($password, Password::ssha384('password', Password::getSalt($password)));
    }

    public function test_ssha512()
    {
        $password = Password::ssha512('password');

        $this->assertNotEquals($password, Password::ssha512('password'));
        $this->assertEquals($password, Password::ssha512('password', Password::getSalt($password)));
    }

    public function test_smd5()
    {
        $password = Password::smd5('password');

        $this->assertNotEquals($password, Password::smd5('password'));
        $this->assertEquals($password, Password::smd5('password', Password::getSalt($password)));
    }

    public function test_md5crypt()
    {
        $password = Password::md5crypt('password');

        $this->assertNotEquals($password, Password::md5crypt('password'));
        $this->assertEquals($password, Password::md5crypt('password', Password::getSalt($password)));
    }

    public function test_sha256crypt()
    {
        $password = Password::sha256crypt('password');

        $this->assertNotEquals($password, Password::sha256crypt('password'));
        $this->assertEquals($password, Password::sha256crypt('password', Password::getSalt($password)));
    }

    public function test_sha512crypt()
    {
        $password = Password::sha512crypt('password');

        $this->assertNotEquals($password, Password::sha512crypt('password'));
        $this->assertEquals($password, Password::sha512crypt('password', Password::getSalt($password)));
    }

    // Unsalted Hash Tests. //

    public function test_sha()
    {
        $this->assertEquals(
            '{SHA}W6ph5Mm5Pz8GgiULbPgzG37mj9g=',
            Password::sha('password')
        );
    }

    public function test_sha256()
    {
        $this->assertEquals(
            '{SHA256}XohImNooBHFR0OVvjcYpJ3NgPQ1qq73WKhHvch0VQtg=',
            Password::sha256('password')
        );
    }

    public function test_sha384()
    {
        $this->assertEquals(
            '{SHA384}qLZLq9CsqRpZvbt3YbQh1PK7OCgNOnW6DyHyvrxFWD1EbFmGYMlM5oDEfRnDB4On',
            Password::sha384('password')
        );
    }

    public function test_sha512()
    {
        $this->assertEquals(
            '{SHA512}sQnzu7wkTrgkQZF+0G1hi5AI3Qmzvv0bXgc5THBqi7mAsdd4Xll27ASbRt9fEyavWi6m0QP9B8lThf+rDKy8hg==',
            Password::sha512('password')
        );
    }

    public function test_md5()
    {
        $this->assertEquals(
            '{MD5}X03MO1qnZdYdgyfeuILPmQ==',
            Password::md5('password')
        );
    }

    public function test_nthash()
    {
        $this->assertEquals(
            '{NTHASH}8846F7EAEE8FB117AD06BDD830B7586C',
            Password::nthash('password')
        );
    }

    // Utility tests. //

    public function test_get_hash_method()
    {
        $password = '{CRYPT}$6$77JasHs4YajlH$882VlypqZqKXT0d1vQsdBoCLHjYTzqRnxwy3qKiBCARaHvXhhQPv80JBIsfv25pm/fTLAc0dxdW1DTHA7e5QU1';

        $this->assertEquals('CRYPT', Password::getHashMethod($password));
        $this->assertNull(Password::getHashMethod('invalid'));
    }

    public function test_get_hash_method_and_algo()
    {
        $password = '{CRYPT}$6$77JasHs4YajlH$882VlypqZqKXT0d1vQsdBoCLHjYTzqRnxwy3qKiBCARaHvXhhQPv80JBIsfv25pm/fTLAc0dxdW1DTHA7e5QU1';

        [$method, $algo] = Password::getHashMethodAndAlgo($password);

        $this->assertEquals('CRYPT', $method);
        $this->assertEquals('6', $algo);

        $this->assertNull(Password::getHashMethodAndAlgo('invalid'));
    }
}
