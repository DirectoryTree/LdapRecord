<?php

namespace LdapRecord\Tests\Models\Attributes;

use LdapRecord\Tests\TestCase;
use LdapRecord\Models\Attributes\Password;

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
        $password1 = Password::ssha('password');
        $password2 = Password::ssha('password');

        $this->assertNotEquals(
            $password1,
            $password2
        );

        $this->assertEquals(
            $password1, 
            Password::ssha('password', Password::getSalt($password1))
        );
    }

    public function test_ssha256()
    {
        $password1 = Password::ssha256('password');
        $password2 = Password::ssha256('password');

        $this->assertNotEquals(
            $password1,
            $password2
        );

        $this->assertEquals(
            $password1, 
            Password::ssha256('password', Password::getSalt($password1))
        );        
    }

    public function test_ssha384()
    {
        $password1 = Password::ssha384('password');
        $password2 = Password::ssha384('password');

        $this->assertNotEquals(
            $password1,
            $password2
        );

        $this->assertEquals(
            $password1, 
            Password::ssha384('password', Password::getSalt($password1))
        );
    }

    public function test_ssha512()
    {
        $password1 = Password::ssha512('password');
        $password2 = Password::ssha512('password');

        $this->assertNotEquals(
            $password1,
            $password2
        );

        $this->assertEquals(
            $password1, 
            Password::ssha512('password', Password::getSalt($password1))
        );
    }

    public function test_smd5()
    {
        $password1 = Password::smd5('password');
        $password2 = Password::smd5('password');

        $this->assertNotEquals(
            $password1,
            $password2
        );

        $this->assertEquals(
            $password1, 
            Password::smd5('password', Password::getSalt($password1))
        );
    }



    public function test_md5crypt()
    {
        $password1 = Password::md5crypt('password');
        $password2 = Password::md5crypt('password');

        $this->assertNotEquals(
            $password1,
            $password2
        );

        $this->assertEquals(
            $password1, 
            Password::md5crypt('password', Password::getSalt($password1))
        );
    }

    public function test_sha256crypt()
    {
        $password1 = Password::sha256crypt('password');
        $password2 = Password::sha256crypt('password');

        $this->assertNotEquals(
            $password1,
            $password2
        );

        $this->assertEquals(
            $password1, 
            Password::sha256crypt('password', Password::getSalt($password1))
        );
    }

    public function test_sha512crypt()
    {
        $password1 = Password::sha512crypt('password');
        $password2 = Password::sha512crypt('password');

        $this->assertNotEquals(
            $password1,
            $password2
        );

        $this->assertEquals(
            $password1, 
            Password::sha512crypt('password', Password::getSalt($password1))
        );
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
}
