<?php


namespace LdapRecord\Models\ActiveDirectory;

use InvalidArgumentException;
use LdapRecord\Models\Attributes\Sid;
use LdapRecord\Models\Entry as BaseEntry;
use LdapRecord\Models\Types\ActiveDirectory;

class Entry extends BaseEntry implements ActiveDirectory
{
    /**
     * The attribute key that contains the Object SID.
     *
     * @var string
     */
    protected $sidKey = 'objectsid';

    /**
     * {@inheritDoc}
     */
    public function getObjectSidKey()
    {
        return $this->sidKey;
    }

    /**
     * {@inheritDoc}
     */
    public function getObjectSid()
    {
        return $this->getFirstAttribute($this->sidKey);
    }

    /**
     * {@inheritDoc}
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
     * Converts attributes for JSON serialization.
     *
     * @param array $attributes
     *
     * @return array
     */
    protected function convertAttributesForJson(array $attributes = [])
    {
        return array_replace($attributes, [
            $this->guidKey => $this->getConvertedGuid(),
            $this->sidKey  => $this->getConvertedSid(),
        ]);
    }
}
