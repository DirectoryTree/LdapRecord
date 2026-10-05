<?php

namespace LdapRecord\Tests\Unit;

use LdapRecord\Auth\Events\Attempting;
use LdapRecord\Auth\Guard;
use LdapRecord\Configuration\DomainConfiguration;
use LdapRecord\Connection;
use LdapRecord\ConnectionException;
use LdapRecord\Container;
use LdapRecord\Events\Dispatcher;
use LdapRecord\Ldap;
use LdapRecord\LdapRecordException;
use LdapRecord\Models\ActiveDirectory\User;
use LdapRecord\Models\Attributes\Password;
use LdapRecord\Models\OpenLDAP\User as OpenLDAPUser;
use LdapRecord\Tests\TestCase;
use Mockery as m;
use RuntimeException;
use SensitiveParameterValue;
use Throwable;

/**
 * @requires PHP 8.2
 */
class SensitiveParameterTest extends TestCase
{
    protected string $exceptionIgnoreArgs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->exceptionIgnoreArgs = ini_get('zend.exception_ignore_args');

        ini_set('zend.exception_ignore_args', '0');
    }

    protected function tearDown(): void
    {
        ini_set('zend.exception_ignore_args', $this->exceptionIgnoreArgs);

        parent::tearDown();
    }

    public function test_authentication_listener_failures_redact_the_password()
    {
        $password = 'sensitive-authentication-password';
        $dispatcher = new Dispatcher;
        $dispatcher->listen(Attempting::class, function (Attempting $event) use ($password) {
            $this->assertSame($password, $event->getPassword());

            throw new RuntimeException('Listener failed.');
        });

        $guard = new Guard(new Ldap, new DomainConfiguration);
        $guard->setDispatcher($dispatcher);

        try {
            $guard->attempt('cn=jdoe,dc=local,dc=com', $password);
        } catch (RuntimeException $exception) {
            $trace = json_encode($exception->getTrace(), JSON_THROW_ON_ERROR);

            $this->assertStringNotContainsString($password, $trace);
            $this->assertStringContainsString('cn=jdoe,dc=local,dc=com', $trace);

            return;
        }

        $this->fail('The authentication listener should have thrown an exception.');
    }

    /**
     * @dataProvider modelAttributeOperations
     */
    public function test_model_attribute_failures_redact_passwords(string $operation)
    {
        Container::addConnection(new Connection);

        $user = new User;
        $user->setRawAttributes(['dn' => ['cn=jdoe,dc=local,dc=com']]);
        $password = 'sensitive-model-password';

        try {
            match ($operation) {
                'property' => $user->unicodepwd = $password,
                'array' => $user['unicodepwd'] = $password,
                'setAttribute' => $user->setAttribute('unicodepwd', $password),
                'setFirstAttribute' => $user->setFirstAttribute('unicodepwd', $password),
                'fill' => $user->fill(['unicodepwd' => $password]),
                'save' => $user->save(['unicodepwd' => $password]),
                'saveQuietly' => $user->saveQuietly(['unicodepwd' => $password]),
                'update' => $user->update(['unicodepwd' => $password]),
                'create' => User::create(['unicodepwd' => $password]),
                'make' => User::make(['unicodepwd' => $password]),
                'newInstance' => $user->newInstance(['unicodepwd' => $password]),
                'constructor' => new User(['unicodepwd' => $password]),
            };
        } catch (ConnectionException $exception) {
            $trace = json_encode($exception->getTrace(), JSON_THROW_ON_ERROR);

            $this->assertStringNotContainsString($password, $trace);
            $this->assertStringContainsString('setUnicodepwdAttribute', $trace);

            return;
        }

        $this->fail('The password assignment should require a secure connection.');
    }

    public static function modelAttributeOperations(): array
    {
        return [
            ['property'],
            ['array'],
            ['setAttribute'],
            ['setFirstAttribute'],
            ['fill'],
            ['save'],
            ['saveQuietly'],
            ['update'],
            ['create'],
            ['make'],
            ['newInstance'],
            ['constructor'],
        ];
    }

    public function test_openldap_password_change_failures_redact_both_passwords()
    {
        Container::addConnection(new Connection(['use_tls' => true]));

        $user = new OpenLDAPUser;
        $oldPassword = 'sensitive-current-password';
        $newPassword = 'sensitive-new-password';

        try {
            $user->changePassword($oldPassword, $newPassword);
        } catch (LdapRecordException $exception) {
            $arguments = $exception->getTrace()[0]['args'];

            $this->assertInstanceOf(SensitiveParameterValue::class, $arguments[0]);
            $this->assertInstanceOf(SensitiveParameterValue::class, $arguments[1]);
            $this->assertSame($oldPassword, $arguments[0]->getValue());
            $this->assertSame($newPassword, $arguments[1]->getValue());

            return;
        }

        $this->fail('The password change should require an existing model.');
    }

    public function test_connection_password_change_failures_redact_both_passwords()
    {
        $connection = m::mock(Connection::class.'[replicate]', [[]]);
        $connection->shouldReceive('replicate')->once()->andReturnUsing(function () {
            throw new RuntimeException('Cannot replicate connection.');
        });
        $oldPassword = 'sensitive-current-password';
        $newPassword = 'sensitive-new-password';

        try {
            $connection->changePassword('cn=jdoe,dc=local,dc=com', $oldPassword, $newPassword);
        } catch (RuntimeException $exception) {
            $trace = json_encode($exception->getTrace(), JSON_THROW_ON_ERROR);

            $this->assertStringNotContainsString($oldPassword, $trace);
            $this->assertStringNotContainsString($newPassword, $trace);
            $this->assertStringContainsString('cn=jdoe,dc=local,dc=com', $trace);

            return;
        }

        $this->fail('The connection replication should have thrown an exception.');
    }

    /**
     * @dataProvider bindOperations
     */
    public function test_ldap_bind_failures_redact_passwords(string $operation)
    {
        $ldap = new Ldap;
        $password = 'sensitive-bind-password';

        try {
            $ldap->{$operation}('cn=jdoe,dc=local,dc=com', $password);
        } catch (Throwable $exception) {
            $trace = $exception->getTrace();
            $encodedTrace = json_encode($trace, JSON_THROW_ON_ERROR);

            $this->assertStringNotContainsString($password, $encodedTrace);
            $this->assertStringContainsString('cn=jdoe,dc=local,dc=com', $encodedTrace);

            return;
        }

        $this->fail('The bind should require an LDAP connection.');
    }

    public static function bindOperations(): array
    {
        return [
            ['bind'],
            ['saslBind'],
        ];
    }

    public function test_password_modify_failures_redact_both_passwords()
    {
        $ldap = new Ldap;
        $oldPassword = 'sensitive-current-password';
        $newPassword = 'sensitive-new-password';

        try {
            $ldap->exopPasswd('cn=jdoe,dc=local,dc=com', $oldPassword, $newPassword);
        } catch (Throwable $exception) {
            $trace = json_encode($exception->getTrace(), JSON_THROW_ON_ERROR);

            $this->assertStringNotContainsString($oldPassword, $trace);
            $this->assertStringNotContainsString($newPassword, $trace);
            $this->assertStringContainsString('cn=jdoe,dc=local,dc=com', $trace);

            return;
        }

        $this->fail('The password modify operation should require an LDAP connection.');
    }

    public function test_password_hashing_failures_redact_passwords()
    {
        $password = 'sensitive-password-without-a-hash-prefix';

        try {
            Password::getSalt($password);
        } catch (LdapRecordException $exception) {
            $argument = $exception->getTrace()[0]['args'][0];

            $this->assertInstanceOf(SensitiveParameterValue::class, $argument);
            $this->assertSame($password, $argument->getValue());

            return;
        }

        $this->fail('Extracting a salt should require a recognized password hash.');
    }
}
