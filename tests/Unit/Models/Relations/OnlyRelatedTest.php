<?php

namespace LdapRecord\Tests\Unit\Models\Relations;

use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\Models\ActiveDirectory\Computer;
use LdapRecord\Models\ActiveDirectory\Group;
use LdapRecord\Models\ActiveDirectory\User;
use LdapRecord\Models\Entry;
use LdapRecord\Models\Relations\HasMany;
use LdapRecord\Models\Relations\HasManyIn;
use LdapRecord\Models\Relations\HasOne;
use LdapRecord\Models\Relations\Relation;
use LdapRecord\Testing\DirectoryFake;
use LdapRecord\Testing\LdapFake;
use LdapRecord\Tests\TestCase;

class OnlyRelatedTest extends TestCase
{
    public function test_users_only_relationship_excludes_groups_and_computers()
    {
        Container::addConnection(new Connection);

        DirectoryFake::setup()->getLdapConnection()->expect(
            LdapFake::operation('search')->once()->andReturn([
                ['dn' => 'cn=Group,dc=local', 'objectclass' => Group::$objectClasses],
                ['dn' => 'cn=Computer,dc=local', 'objectclass' => Computer::$objectClasses],
                ['dn' => 'cn=User,dc=local', 'objectclass' => User::$objectClasses],
            ])
        );

        $parent = (new Entry)->setDn('cn=Parent,dc=local');
        $relation = new HasMany($parent->newQuery(), $parent, User::class, 'member', 'dn', 'members');
        $results = $relation->onlyRelated()->get();

        $this->assertCount(1, $results);
        $this->assertInstanceOf(User::class, $results[0]);
        $this->assertSame('cn=User,dc=local', $results[0]->getDn());
    }

    public function test_relationship_accepts_each_declared_model_type()
    {
        Container::addConnection(new Connection);

        DirectoryFake::setup()->getLdapConnection()->expect(
            LdapFake::operation('search')->once()->andReturn([
                ['dn' => 'cn=Computer,dc=local', 'objectclass' => Computer::$objectClasses],
                ['dn' => 'cn=Group,dc=local', 'objectclass' => Group::$objectClasses],
                ['dn' => 'cn=User,dc=local', 'objectclass' => User::$objectClasses],
            ])
        );

        $parent = (new Entry)->setDn('cn=Parent,dc=local');
        $relation = new HasMany($parent->newQuery(), $parent, [User::class, Group::class], 'member', 'dn', 'members');
        $results = $relation->onlyRelated()->get();

        $this->assertCount(2, $results);
        $this->assertInstanceOf(Group::class, $results[0]);
        $this->assertInstanceOf(User::class, $results[1]);
    }

    public function test_has_many_in_excludes_unrelated_foreign_entries()
    {
        Container::addConnection(new Connection);

        DirectoryFake::setup()->getLdapConnection()->expect([
            LdapFake::operation('read')->once()->with('cn=Group,dc=local')->andReturn([
                ['dn' => 'cn=Group,dc=local', 'objectclass' => Group::$objectClasses],
            ]),
            LdapFake::operation('read')->once()->with('cn=User,dc=local')->andReturn([
                ['dn' => 'cn=User,dc=local', 'objectclass' => User::$objectClasses],
            ]),
        ]);

        $parent = new Entry;
        $parent->member = ['cn=Group,dc=local', 'cn=User,dc=local'];
        $relation = new HasManyIn($parent->newQuery(), $parent, User::class, 'member', 'dn', 'members');
        $results = $relation->onlyRelated()->get();

        $this->assertCount(1, $results);
        $this->assertInstanceOf(User::class, $results[0]);
        $this->assertSame('cn=User,dc=local', $results[0]->getDn());
    }

    public function test_has_one_excludes_an_unrelated_foreign_entry()
    {
        Container::addConnection(new Connection);

        DirectoryFake::setup()->getLdapConnection()->expect(
            LdapFake::operation('read')->once()->with('cn=Group,dc=local')->andReturn([
                ['dn' => 'cn=Group,dc=local', 'objectclass' => Group::$objectClasses],
            ])
        );

        $parent = new Entry;
        $parent->manager = 'cn=Group,dc=local';
        $relation = new HasOne($parent->newQuery(), $parent, User::class, 'manager', 'dn');

        $this->assertNull($relation->onlyRelated()->first());
    }

    public function test_only_related_uses_the_custom_model_resolver()
    {
        Container::addConnection(new Connection);

        DirectoryFake::setup()->getLdapConnection()->expect(
            LdapFake::operation('read')->once()->with('cn=User,dc=local')->andReturn([
                ['dn' => 'cn=User,dc=local', 'objectclass' => [...User::$objectClasses, 'custom']],
            ])
        );

        $parent = new Entry;
        $parent->manager = 'cn=User,dc=local';
        $relation = new HasOne($parent->newQuery(), $parent, User::class, 'manager', 'dn');

        Relation::resolveModelsUsing(fn () => User::class);

        try {
            $this->assertInstanceOf(User::class, $relation->onlyRelated()->first());
        } finally {
            Relation::resolveModelsUsing(null);
        }
    }

    public function test_only_related_excludes_undeclared_models_from_merged_relationships()
    {
        Container::addConnection(new Connection);

        DirectoryFake::setup()->getLdapConnection()->expect([
            LdapFake::operation('search')->once()->andReturn([
                ['dn' => 'cn=User,dc=local', 'objectclass' => User::$objectClasses],
            ]),
            LdapFake::operation('read')->once()->with('cn=Group,dc=local')->andReturn([
                ['dn' => 'cn=Group,dc=local', 'objectclass' => Group::$objectClasses],
            ]),
        ]);

        $parent = (new Entry)->setDn('cn=Parent,dc=local');
        $parent->manager = 'cn=Group,dc=local';
        $relation = new HasMany($parent->newQuery(), $parent, User::class, 'member', 'dn', 'members');
        $relation->with(new HasOne($parent->newQuery(), $parent, Group::class, 'manager', 'dn'));
        $results = $relation->onlyRelated()->get();

        $this->assertCount(1, $results);
        $this->assertInstanceOf(User::class, $results[0]);
    }

    public function test_only_related_excludes_undeclared_models_from_recursive_chunks()
    {
        Container::addConnection(new Connection);

        DirectoryFake::setup()->getLdapConnection()->expect([
            LdapFake::operation('search')->once()->andReturn([
                ['dn' => 'cn=User,dc=local', 'objectclass' => User::$objectClasses],
            ]),
            LdapFake::operation('search')->once()->andReturn([
                ['dn' => 'cn=Group,dc=local', 'objectclass' => Group::$objectClasses],
                ['dn' => 'cn=Report,dc=local', 'objectclass' => User::$objectClasses],
            ]),
            LdapFake::operation('search')->once()->andReturn([]),
        ]);

        $parent = (new Entry)->setDn('cn=Parent,dc=local');
        $relation = new HasMany($parent->newQuery(), $parent, OnlyRelatedUserStub::class, 'manager', 'dn', 'reports');
        $results = $parent->newCollection();

        $completed = $relation->onlyRelated()->recursive()->chunk(1000, function ($chunk) use ($results) {
            foreach ($chunk as $model) {
                $results->push($model);
            }
        });

        $this->assertTrue($completed);
        $this->assertSame(['cn=User,dc=local', 'cn=Report,dc=local'], $results->map->getDn()->all());
        $this->assertSame([OnlyRelatedUserStub::class, OnlyRelatedUserStub::class], $results->map(fn ($model) => $model::class)->all());
    }

    public function test_only_related_recursive_chunking_can_be_stopped()
    {
        Container::addConnection(new Connection);

        DirectoryFake::setup()->getLdapConnection()->expect([
            LdapFake::operation('search')->once()->andReturn([
                ['dn' => 'cn=User,dc=local', 'objectclass' => User::$objectClasses],
            ]),
            LdapFake::operation('search')->once()->andReturn([
                ['dn' => 'cn=Group,dc=local', 'objectclass' => Group::$objectClasses],
                ['dn' => 'cn=Report,dc=local', 'objectclass' => User::$objectClasses],
            ]),
        ]);

        $parent = (new Entry)->setDn('cn=Parent,dc=local');
        $relation = new HasMany($parent->newQuery(), $parent, OnlyRelatedUserStub::class, 'manager', 'dn', 'reports');
        $results = $parent->newCollection();

        $completed = $relation->onlyRelated()->recursive()->chunk(1000, function ($chunk) use ($results) {
            foreach ($chunk as $model) {
                $results->push($model);

                if ($model->getDn() === 'cn=Report,dc=local') {
                    return false;
                }
            }
        });

        $this->assertFalse($completed);
        $this->assertSame(['cn=User,dc=local', 'cn=Report,dc=local'], $results->map->getDn()->all());
    }
}

class OnlyRelatedUserStub extends User
{
    public function reports(): HasMany
    {
        return $this->hasMany([static::class, Group::class], 'manager');
    }
}
