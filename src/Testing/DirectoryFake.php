<?php

namespace LdapRecord\Testing;

use LdapRecord\Container;

class DirectoryFake
{
    /**
     * Set up the fake connection.
     *
     * @throws \LdapRecord\ContainerException
     */
    public static function setup(string $name = null): ConnectionFake
    {
        $connection = Container::getConnection($name);

        $fake = static::makeConnectionFake(
            $connection->getConfiguration()->all()
        );

        // Replace the connection with a fake.
        Container::addConnection($fake, $name);

        return $fake;
    }

    /**
     * Reset the container.
     */
    public static function tearDown(): void
    {
        Container::flush();
    }

    /**
     * Make a connection fake.
     */
    public static function makeConnectionFake(array $config = []): ConnectionFake
    {
        return ConnectionFake::make($config)->shouldBeConnected();
    }
}
