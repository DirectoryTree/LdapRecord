<?php

namespace LdapRecord\Tests\Unit\Models;

use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\Models\Entry;
use LdapRecord\Models\Model;
use LdapRecord\Models\ModelDoesNotExistException;
use LdapRecord\Testing\DirectoryFake;
use LdapRecord\Testing\LdapFake;
use LdapRecord\Tests\TestCase;

class ModelRenameTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Container::addConnection(new Connection);

        Model::clearBootedModels();
    }

    protected function tearDown(): void
    {
        Container::flush();

        parent::tearDown();
    }

    public function test_rename()
    {
        DirectoryFake::setup()
            ->getLdapConnection()
            ->expect(
                LdapFake::operation('rename')
                    ->with('cn=John Doe,dc=acme,dc=org', 'cn=Jane Doe', 'dc=acme,dc=org')
                    ->andReturnTrue()
            );

        $model = (new Entry)->setRawAttributes([
            'dn' => 'cn=John Doe,dc=acme,dc=org',
        ]);

        $model->rename('cn=Jane Doe');

        $this->assertEquals('cn=Jane Doe,dc=acme,dc=org', $model->getDn());
        $this->assertEquals('Jane Doe', $model->getFirstAttribute('cn'));
        $this->assertSame(['Jane Doe'], $model->getOriginal()['cn']);
        $this->assertTrue($model->wasRecentlyRenamed);
    }

    public function test_rename_with_no_attribute_name()
    {
        DirectoryFake::setup()
            ->getLdapConnection()
            ->expect(
                LdapFake::operation('rename')
                    ->with('cn=John Doe,dc=acme,dc=org', 'cn=Jane Doe', 'dc=acme,dc=org')
                    ->andReturnTrue()
            );

        $model = (new Entry)->setRawAttributes([
            'dn' => 'cn=John Doe,dc=acme,dc=org',
        ]);

        $model->rename('Jane Doe');

        $this->assertEquals('cn=Jane Doe,dc=acme,dc=org', $model->getDn());
        $this->assertEquals('Jane Doe', $model->getFirstAttribute('cn'));
        $this->assertSame(['Jane Doe'], $model->getOriginal()['cn']);
        $this->assertTrue($model->wasRecentlyRenamed);
    }

    public function test_rename_with_new_parent()
    {
        DirectoryFake::setup()
            ->getLdapConnection()
            ->expect(
                LdapFake::operation('rename')
                    ->with('cn=John Doe,dc=acme,dc=org', 'cn=Jane Doe', 'ou=Users,dc=acme,dc=org')
                    ->andReturnTrue()
            );

        $model = (new Entry)->setRawAttributes([
            'dn' => 'cn=John Doe,dc=acme,dc=org',
        ]);

        $model->rename('cn=Jane Doe', 'ou=Users,dc=acme,dc=org');

        $this->assertEquals('cn=Jane Doe,ou=Users,dc=acme,dc=org', $model->getDn());
        $this->assertEquals('Jane Doe', $model->getFirstAttribute('cn'));
        $this->assertSame(['Jane Doe'], $model->getOriginal()['cn']);
        $this->assertTrue($model->wasRecentlyRenamed);
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
        $model = new Entry;

        $this->expectException(ModelDoesNotExistException::class);

        $model->rename('invalid');
    }

    public function test_rename_with_same_name_does_not_send_request()
    {
        $model = new Entry;

        $model->setRawAttributes(['dn' => 'cn=John Doe,dc=acme,dc=org']);

        $model->rename('John Doe');

        $this->assertFalse($model->wasRecentlyRenamed);
    }

    public function test_rename_with_same_rdn_does_not_send_request()
    {
        $model = new Entry;

        $model->setRawAttributes(['dn' => 'cn=John Doe,dc=acme,dc=org']);

        $model->rename('cn=John Doe');

        $this->assertFalse($model->wasRecentlyRenamed);
    }

    public function test_rename_escaping()
    {
        DirectoryFake::setup()
            ->getLdapConnection()
            ->expect(
                LdapFake::operation('rename')
                    ->with('cn=John Doe,dc=acme,dc=org', $rdn = 'cn=Тестирование\2c Имя\2c Побег')
                    ->andReturnTrue()
            );

        $model = new Entry;

        $model->setRawAttributes(['dn' => 'cn=John Doe,dc=acme,dc=org']);

        $model->rename(
            $model->getCreatableRdn('Тестирование, Имя, Побег')
        );

        $this->assertEquals($rdn, $model->getRdn());
        $this->assertTrue($model->wasRecentlyRenamed);
    }

    public function test_rename_escaping_parent()
    {
        DirectoryFake::setup()
            ->getLdapConnection()
            ->expect(
                LdapFake::operation('rename')->with(
                    'cn=Джон Доу,ou=Тест\2C Группа\2C С\2C Запятые,dc=acme,dc=org',
                    $rdn = 'cn=Тестирование\2c Имя\2c Побег'
                )->andReturnTrue()
            );

        $model = new Entry;

        $model->setRawAttributes([
            'dn' => 'cn=Джон Доу,ou=Тест\2C Группа\2C С\2C Запятые,dc=acme,dc=org',
        ]);

        $model->rename(
            $model->getCreatableRdn('Тестирование, Имя, Побег')
        );

        $this->assertEquals($rdn, $model->getRdn());
        $this->assertTrue($model->wasRecentlyRenamed);
    }

    public function test_rename_with_base_dn_substitution()
    {
        Container::addConnection(
            new Connection(['base_dn' => 'dc=local,dc=com'])
        );

        DirectoryFake::setup()
            ->getLdapConnection()
            ->expect(
                LdapFake::operation('rename')
                    ->with('cn=John Doe,dc=acme,dc=org', 'cn=Jane Doe', 'ou=Accounting,dc=local,dc=com')
                    ->andReturnTrue()
            );

        $model = (new Entry)->setRawAttributes([
            'dn' => 'cn=John Doe,dc=acme,dc=org',
        ]);

        $model->rename('Jane Doe', 'ou=Accounting,{base}');

        $this->assertEquals('cn=Jane Doe,ou=Accounting,dc=local,dc=com', $model->getDn());
    }

    public function test_move()
    {
        DirectoryFake::setup()
            ->getLdapConnection()
            ->expect(
                LdapFake::operation('rename')
                    ->with('cn=John Doe,dc=acme,dc=org', 'cn=John Doe', 'ou=Users,dc=acme,dc=org')
                    ->andReturnTrue()
            );

        $model = new Entry;

        $model->setRawAttributes(['dn' => 'cn=John Doe,dc=acme,dc=org']);

        $model->move('ou=Users,dc=acme,dc=org');

        $this->assertEquals('cn=John Doe,ou=Users,dc=acme,dc=org', $model->getDn());
        $this->assertTrue($model->wasRecentlyRenamed);
    }
}
