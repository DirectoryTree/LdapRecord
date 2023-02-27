<?php

namespace LdapRecord\Tests\Integration;

use Carbon\Carbon;
use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\Models\OpenLDAP\OrganizationalUnit;
use LdapRecord\Query\ArrayCacheStore;
use LdapRecord\Tests\Integration\Fixtures\User;
use Psr\SimpleCache\CacheInterface;

class CacheTest extends TestCase
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

    protected function resetConnection(array $params = [], CacheInterface $cache = null): Connection
    {
        Container::reset();

        $connection = parent::makeConnection($params);
        if ($cache) {
            $connection->setCache($cache);
        }

        Container::addConnection($connection);

        return $connection;
    }

    protected function tearDown(): void
    {
        $this->ou->delete(true);

        Container::reset();

        parent::tearDown();
    }

    protected function createUser(string $cn): User
    {
        $user = (new User())
            ->inside($this->ou)
            ->fill(array_merge([
                'uid' => 'u'.$cn,
                'cn' => $cn,
                'sn' => 'Baz',
                'givenName' => $cn,
                'uidNumber' => 1000 + ord($cn),
                'gidNumber' => 1000 + ord($cn),
                'homeDirectory' => '/'.strtolower($cn),
            ]));

        $user->save();

        return $user;
    }

    protected function listUserCNsUsingCache(int $ttl = 30): array
    {
        $cache = Carbon::now()->addSecond($ttl);

        $result = [];
        foreach (User::cache($cache)->get() as $user) {
            $result[] = $user->cn[0];
        }
        sort($result);

        return $result;
    }

    public function test_that_results_are_fetched_from_cache()
    {
        $cache = new ArrayCacheStore();
        $c = $this->resetConnection([], $cache);

        $this->assertEquals([], $this->listUserCNsUsingCache());
        $user = $this->createUser('foo');

        $this->assertEquals([], $this->listUserCNsUsingCache());
    }

    public function test_that_results_are_fetched_from_cache2()
    {
        $cache = new ArrayCacheStore();
        $c = $this->resetConnection([], $cache);

        $user = $this->createUser('foo');
        $this->assertEquals(['foo'], $this->listUserCNsUsingCache());
        $user = $this->createUser('bar');

        $this->assertEquals(['foo'], $this->listUserCNsUsingCache());
    }

    public function test_that_results_expire_from_cache()
    {
        $cache = new ArrayCacheStore();
        $this->resetConnection([], $cache);

        $user = $this->createUser('foo');
        $this->assertEquals(['foo'], $this->listUserCNsUsingCache(1));
        $user = $this->createUser('bar');

        sleep(2);

        $this->assertEquals(['bar', 'foo'], $this->listUserCNsUsingCache());
    }

    public function test_that_results_stay_in_cache_even_if_connection_is_reset()
    {
        $cache = new ArrayCacheStore();
        $this->resetConnection([], $cache);

        $user = $this->createUser('foo');
        $this->assertEquals(['foo'], $this->listUserCNsUsingCache());
        $user = $this->createUser('bar');

        $this->resetConnection([], $cache);

        $this->assertEquals(['foo'], $this->listUserCNsUsingCache());
    }

    public function test_that_results_are_not_reused_if_hostname_changes()
    {
        $cache = new ArrayCacheStore();
        $this->resetConnection([], $cache);

        $user = $this->createUser('foo');
        $this->assertEquals(['foo'], $this->listUserCNsUsingCache());
        $user = $this->createUser('bar');

        $this->resetConnection(['hosts' => ['127.0.0.1']], $cache);

        $this->assertEquals(['bar', 'foo'], $this->listUserCNsUsingCache());
    }
}
