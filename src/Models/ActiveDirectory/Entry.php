<?php

namespace LdapRecord\Models\ActiveDirectory;

use InvalidArgumentException;
use LdapRecord\LdapInterface;
use LdapRecord\Models\Attributes\Sid;
use LdapRecord\Models\Entry as BaseEntry;
use LdapRecord\Models\Types\ActiveDirectory;
use LdapRecord\Query\Model\ActiveDirectoryBuilder;

/** @mixin ActiveDirectoryBuilder */
class Entry extends BaseEntry implements ActiveDirectory
{
    /**
     * The default attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $defaultDates = [
        'whenchanged' => 'windows',
        'whencreated' => 'windows',
    ];

    /**
     * The attribute key that contains the Object SID.
     *
     * @var string
     */
    protected $sidKey = 'objectsid';

    /**
     * {@inheritdoc}
     */
    public function getObjectSidKey()
    {
        return $this->sidKey;
    }

    /**
     * {@inheritdoc}
     */
    public function getObjectSid()
    {
        return $this->getFirstAttribute($this->sidKey);
    }

    /**
     * {@inheritdoc}
     */
    public function getConvertedSid()
    {
        try {
            return (string) new Sid($this->getObjectSid());
        } catch (InvalidArgumentException $e) {
            return;
        }
    }

    /**
     * Create a new query builder.
     *
     * @param LdapInterface $connection
     *
     * @return ActiveDirectoryBuilder
     */
    public function newQueryBuilder(LdapInterface $connection)
    {
        return new ActiveDirectoryBuilder($connection);
    }

    /**
     * Converts attributes for JSON serialization.
     *
     * @param array $attributes
     *
     * @return array
     */
    protected function convertAttributesForJson(array $attributes = [])
    {
        $attributes = parent::convertAttributesForJson($attributes);

        if ($this->hasAttribute($this->sidKey)) {
            // If the model has a SID set, we need to convert it due to it being in
            // binary. Otherwise we will receive a JSON serialization exception.
            return array_replace($attributes, [
                $this->sidKey => [$this->getConvertedSid()],
            ]);
        }

        return $attributes;
    }
}
