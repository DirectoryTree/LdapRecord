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

    protected function tearDown(): void
    {
        $this->ou->delete(true);

        Container::reset();

        parent::tearDown();
    }

    protected function resetConnection(array $params = [], CacheInterface $cache = null)
    {
        Container::reset();

        $connection = parent::makeConnection($params);

        if ($cache) {
            $connection->setCache($cache);
        }

        Container::addConnection($connection);

        return $connection;
    }

    /** @return User */
    protected function createUser(string $cn)
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

    /** @return array */
    protected function getUserCnsFromCache(int $ttl = 30)
    {
        $cache = Carbon::now()->addSeconds($ttl);

        return User::cache($cache)
            ->get()
            ->sortBy(function (User $user) {
                return $user->getName();
            })->map(function (User $user) {
                return $user->getName();
            })
            ->values()
            ->all();
    }

    public function test_that_results_are_fetched_from_cache()
    {
        $cache = new ArrayCacheStore();
        $this->resetConnection([], $cache);

        $this->assertEquals([], $this->getUserCnsFromCache());
        $this->createUser('foo');

        $this->assertEquals([], $this->getUserCnsFromCache());
    }

    public function test_that_results_are_fetched_from_cache2()
    {
        $cache = new ArrayCacheStore();
        $this->resetConnection([], $cache);

        $this->createUser('foo');
        $this->assertEquals(['foo'], $this->getUserCnsFromCache());
        $this->createUser('bar');

        $this->assertEquals(['foo'], $this->getUserCnsFromCache());
    }

    public function test_that_results_expire_from_cache()
    {
        $cache = new ArrayCacheStore();
        $this->resetConnection([], $cache);

        $this->createUser('foo');
        $this->assertEquals(['foo'], $this->getUserCnsFromCache(1));
        $this->createUser('bar');

        sleep(2);

        $this->assertEquals(['bar', 'foo'], $this->getUserCnsFromCache());
    }

    public function test_that_results_stay_in_cache_even_if_connection_is_reset()
    {
        $cache = new ArrayCacheStore();
        $this->resetConnection([], $cache);

        $this->createUser('foo');
        $this->assertEquals(['foo'], $this->getUserCnsFromCache());
        $this->createUser('bar');

        $this->resetConnection([], $cache);

        $this->assertEquals(['foo'], $this->getUserCnsFromCache());
    }

    public function test_that_results_are_not_reused_if_hostname_changes()
    {
        $cache = new ArrayCacheStore();
        $this->resetConnection([], $cache);

        $this->createUser('foo');
        $this->assertEquals(['foo'], $this->getUserCnsFromCache());
        $this->createUser('bar');

        $this->resetConnection(['hosts' => ['127.0.0.1']], $cache);

        $this->assertEquals(['bar', 'foo'], $this->getUserCnsFromCache());
    }
}
