<?php

namespace LdapRecord\Tests\Unit\Models;

use LdapRecord\Models\ActiveDirectory\User;
use LdapRecord\Models\Collection;
use LdapRecord\Models\Model;
use LdapRecord\Tests\TestCase;

class CollectionTest extends TestCase
{
    public function test_exists_with_no_parameters()
    {
        $collection = new Collection;

        $this->assertFalse(
            $collection->exists()
        );

        $collection = new Collection([new User]);

        $this->assertTrue(
            $collection->exists()
        );
    }

    public function test_exists_with_dn()
    {
        $user = new User(['dn' => 'cn=John Doe']);

        $collection = new Collection([$user]);

        $this->assertTrue(
            $collection->exists('cn=John Doe')
        );

        $this->assertFalse(
            $collection->exists('cn=Jane Doe')
        );
    }

    public function test_exists_with_multiple_dns()
    {
        $user = new User(['dn' => 'cn=John Doe']);

        $collection = new Collection([$user]);

        $this->assertTrue(
            $collection->exists(['cn=John Doe'])
        );

        $this->assertFalse(
            $collection->exists(['cn=Jane Doe', 'cn=Foo Bar'])
        );
    }

    public function test_exists_with_model()
    {
        $user = new User(['dn' => 'cn=John Doe']);

        $collection = new Collection([$user]);

        $this->assertTrue(
            $collection->exists($user)
        );

        $this->assertFalse(
            $collection->exists(new User(['dn' => 'cn=Jane Doe']))
        );
    }

    public function test_exists_with_model_collection()
    {
        $user = new User(['dn' => 'cn=John Doe']);

        $collection = new Collection([$user]);

        $this->assertTrue(
            $collection->exists(new Collection([$user]))
        );

        $this->assertFalse(
            $collection->exists(new Collection([
                new User(['dn' => 'cn=Jane Doe']),
                new User(['dn' => 'cn=Foo Bar']),
            ]))
        );
    }

    public function test_contains_with_closure()
    {
        $user = new User(['cn' => 'John Doe']);

        $collection = new Collection([$user]);

        $this->assertTrue(
            $collection->contains(function (Model $model) {
                return $model->getFirstAttribute('cn') === 'John Doe';
            })
        );

        $this->assertFalse(
            $collection->contains(function (Model $model) {
                return $model->getFirstAttribute('cn') === 'Jane Doe';
            })
        );
    }

    public function test_contains_with_key_operator_and_value()
    {
        $user = new User(['cn' => 'John Doe']);

        $collection = new Collection([$user]);

        $this->assertTrue(
            $collection->contains('cn', '=', ['John Doe'])
        );

        $this->assertFalse(
            $collection->contains('cn', '=', ['Jane Doe'])
        );
    }

    public function test_contains_with_model()
    {
        $user = new User(['dn' => 'cn=John Doe']);

        $otherUser = new User(['dn' => 'cn=Jane Doe']);

        $collection = new Collection([$user]);

        $this->assertTrue(
            $collection->contains($user)
        );

        $this->assertFalse(
            $collection->contains($otherUser)
        );
    }

    public function test_contains_with_model_without_dn()
    {
        $user = new User(['cn' => 'John Doe']);

        $collection = new Collection([$user]);

        $this->assertFalse(
            $collection->contains(new User)
        );
    }

    public function test_contains_with_multiple_models()
    {
        $user = new User(['dn' => 'cn=John Doe']);

        $otherUser = new User(['dn' => 'cn=Jane Doe']);

        $collection = new Collection([$user, $otherUser]);

        $this->assertTrue(
            $collection->contains([
                $user,
                $otherUser,
            ])
        );

        $this->assertFalse(
            $collection->contains([
                new User(['dn' => 'cn=Foo Bar']),
                new User(['dn' => 'cn=Bar Baz']),
            ])
        );
    }

    public function test_contains_with_model_collection()
    {
        $user = new User(['dn' => 'cn=John Doe']);

        $otherUser = new User(['dn' => 'cn=Jane Doe']);

        $collection = new Collection([$user, $otherUser]);

        $this->assertTrue(
            $collection->contains(new Collection([$user]))
        );

        $this->assertFalse(
            $collection->contains(new Collection([
                new User(['dn' => 'cn=Foo Bar']),
                new User(['dn' => 'cn=Bar Baz']),
            ]))
        );
    }
}
