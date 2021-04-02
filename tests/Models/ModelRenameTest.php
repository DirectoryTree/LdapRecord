<?php

namespace LdapRecord\Tests\Models;

use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\Models\Entry;
use LdapRecord\Models\Model;
use LdapRecord\Models\ModelDoesNotExistException;
use LdapRecord\Query\Model\Builder;
use LdapRecord\Testing\DirectoryFake;
use LdapRecord\Testing\LdapFake;
use LdapRecord\Tests\TestCase;
use Mockery as m;

class ModelRenameTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Container::addConnection(new Connection());

        Model::clearBootedModels();
    }

    protected function tearDown(): void
    {
        Container::reset();

        parent::tearDown();
    }

    public function test_rename()
    {
        $model = new ModelRenameTestStub();
        $model->setRawAttributes(['dn' => 'cn=John Doe,dc=acme,dc=org']);

        $model->rename('cn=Jane Doe');

        $this->assertEquals('cn=Jane Doe,dc=acme,dc=org', $model->getDn());
        $this->assertEquals('Jane Doe', $model->getFirstAttribute('cn'));
        $this->assertSame(['Jane Doe'], $model->getOriginal()['cn']);
    }

    public function test_rename_with_parent()
    {
        $model = new ModelRenameWithParentTestStub();
        $model->setRawAttributes(['dn' => 'cn=John Doe,dc=acme,dc=org']);

        $model->rename('cn=Jane Doe', 'ou=Users,dc=acme,dc=org');

        $this->assertEquals('cn=Jane Doe,ou=Users,dc=acme,dc=org', $model->getDn());
        $this->assertEquals('Jane Doe', $model->getFirstAttribute('cn'));
        $this->assertSame(['Jane Doe'], $model->getOriginal()['cn']);
    }

    public function test_rename_does_not_occur_when_given_the_same_rdn_and_parent_dn()
    {
        $model = (new Entry)->setRawAttributes([
            'dn' => 'cn=John Doe,dc=acme,dc=org',
        ]);

        $model->rename('cn=John Doe');

        $this->assertFalse($model->wasRecentlyRenamed);
    }

    public function test_rename_without_existing_model()
    {
        $model = new Entry();

        $this->expectException(ModelDoesNotExistException::class);

        $model->rename('invalid');
    }

    public function test_rename_escaping()
    {
        DirectoryFake::setup()
            ->getLdapConnection()
            ->expect(
                LdapFake::operation('rename')->with('cn=John Doe,dc=acme,dc=org', 'cn=Тестирование\2c Имя\2c Побег')
            );

        $model = new Entry();

        $model->setRawAttributes(['dn' => 'cn=John Doe,dc=acme,dc=org']);

        $model->rename(
            $model->getCreatableRdn('Тестирование, Имя, Побег')
        );
    }

    public function test_move()
    {
        DirectoryFake::setup()
            ->getLdapConnection()
            ->expect(
                LdapFake::operation('rename')->with('cn=John Doe,dc=acme,dc=org', 'cn=John Doe', 'ou=Users,dc=acme,dc=org')
            );

        $model = new Entry();

        $model->setRawAttributes(['dn' => 'cn=John Doe,dc=acme,dc=org']);

        $model->move('ou=Users,dc=acme,dc=org');

        $this->assertEquals('cn=John Doe,ou=Users,dc=acme,dc=org', $model->getDn());
    }
}

class ModelRenameTestStub extends Model
{
    public function newQuery()
    {
        $builder = m::mock(Builder::class);
        $builder->shouldReceive('rename')
            ->with('cn=John Doe,dc=acme,dc=org', 'cn=Jane Doe', 'dc=acme,dc=org', true)
            ->once()
            ->andReturnTrue();

        return $builder;
    }
}

class ModelRenameWithParentTestStub extends Model
{
    public function newQuery()
    {
        $builder = m::mock(Builder::class);
        $builder->shouldReceive('rename')
            ->with('cn=John Doe,dc=acme,dc=org', 'cn=Jane Doe', 'ou=Users,dc=acme,dc=org', true)
            ->once()
            ->andReturnTrue();

        return $builder;
    }
}

class ModelMoveTestStub extends Model
{
    public function newQuery()
    {
        $builder = m::mock(Builder::class);
        $builder->shouldReceive('rename')
            ->with('cn=John Doe,dc=acme,dc=org', 'cn=John Doe', 'ou=Users,dc=acme,dc=org', true)
            ->once()
            ->andReturnTrue();

        return $builder;
    }
}
