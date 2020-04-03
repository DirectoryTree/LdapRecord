<?php

namespace LdapRecord\Models\Concerns;

use Carbon\Carbon;
use Tightenco\Collect\Support\Arr;
use LdapRecord\LdapRecordException;
use LdapRecord\Models\Attributes\Timestamp;

trait HasAttributes
{
    /**
     * The models original attributes.
     *
     * @var array
     */
    protected $original = [];

    /**
     * The models attributes.
     *
     * @var array
     */
    protected $attributes = [];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [];

    /**
     * The default attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $defaultDates = [
        'createtimestamp' => 'ldap',
        'modifytimestamp' => 'ldap',
    ];

    /**
     * Set the model's original attributes with the model's current attributes.
     *
     * @return $this
     */
    public function syncOriginal()
    {
        $this->original = $this->attributes;

        return $this;
    }

    /**
     * Fills the entry with the supplied attributes.
     *
     * @param array $attributes
     *
     * @return $this
     */
    public function fill(array $attributes = [])
    {
        foreach ($attributes as $key => $value) {
            $this->setAttribute($key, $value);
        }

        return $this;
    }

    /**
     * Returns the models attribute by its key.
     *
     * @param int|string $key
     *
     * @return mixed
     */
    public function getAttribute($key)
    {
        if (!$key) {
            return;
        }

        return $this->getAttributeValue($key);
    }

    /**
     * Get an attribute value.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function getAttributeValue($key)
    {
        $key = $this->normalizeAttributeKey($key);
        $value = $this->getAttributeFromArray($key);

        if ($this->hasGetMutator($key)) {
            return $this->getMutatedAttributeValue($key, $value);
        }

        if ($this->isDateAttribute($key) && !is_null($value)) {
            return $this->asDateTime($this->getDates()[$key], Arr::first($value));
        }

        return $value;
    }

    /**
     * Determine if the given attribute is a date.
     *
     * @param string $key
     *
     * @return bool
     */
    public function isDateAttribute($key)
    {
        return array_key_exists($key, $this->getDates());
    }

    /**
     * Get the attributes that should be mutated to dates.
     *
     * @return array
     */
    public function getDates()
    {
        // Since array string keys can be unique depending on casing differences,
        // we need to normalize the array key case so they are merged properly.
        return array_merge(
            array_change_key_case($this->defaultDates, CASE_LOWER),
            array_change_key_case($this->dates, CASE_LOWER)
        );
    }

    /**
     * Get an attribute from the $attributes array.
     *
     * @param string $key
     *
     * @return mixed
     */
    protected function getAttributeFromArray($key)
    {
        return $this->getNormalizedAttributes()[$key] ?? null;
    }

    /**
     * Get the attributes with their keys normalized.
     *
     * @return array
     */
    protected function getNormalizedAttributes()
    {
        return array_change_key_case($this->attributes, CASE_LOWER);
    }

    /**
     * Returns the first attribute by the specified key.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function getFirstAttribute($key)
    {
        return Arr::first($this->getAttribute($key));
    }

    /**
     * Returns all of the models attributes.
     *
     * @return array
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * Sets an attributes value by the specified key and sub-key.
     *
     * @param mixed $key
     * @param mixed $value
     *
     * @return $this
     */
    public function setAttribute($key, $value)
    {
        $key = $this->normalizeAttributeKey($key);

        if ($this->hasSetMutator($key)) {
            return $this->setMutatedAttributeValue($key, $value);
        } elseif (
            $value &&
            $this->isDateAttribute($key) &&
            !$this->valueIsResetInteger($value)
        ) {
            $value = $this->fromDateTime($this->getDates()[$key], $value);
        }

        $this->attributes[$key] = Arr::wrap($value);

        return $this;
    }

    /**
     * Determine if the given value is an LDAP reset integer.
     *
     * The integer values '0' and '-1' can be used on certain
     * LDAP attributes to instruct the server to reset the
     * value to an 'unset' or 'cleared' state.
     *
     * @param mixed $value
     *
     * @return bool
     */
    protected function valueIsResetInteger($value)
    {
        return in_array($value, [0, -1], $strict = true);
    }

    /**
     * Set the models first attribute value.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return $this
     */
    public function setFirstAttribute($key, $value)
    {
        return $this->setAttribute($key, Arr::wrap($value));
    }

    /**
     * Add a unique value to the given attribute.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return $this
     */
    public function addAttributeValue($key, $value)
    {
        return $this->setAttribute($key, array_unique(
            array_merge(
                Arr::wrap($this->getAttribute($key)),
                Arr::wrap($value)
            )
        ));
    }

    /**
     * Determine if a get mutator exists for an attribute.
     *
     * @param string $key
     *
     * @return bool
     */
    public function hasGetMutator($key)
    {
        return method_exists($this, 'get'.$this->getMutatorMethodName($key).'Attribute');
    }

    /**
     * Determine if a set mutator exists for an attribute.
     *
     * @param string $key
     *
     * @return bool
     */
    public function hasSetMutator($key)
    {
        return method_exists($this, 'set'.$this->getMutatorMethodName($key).'Attribute');
    }

    /**
     * Set the value of an attribute using its mutator.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return mixed
     */
    protected function setMutatedAttributeValue($key, $value)
    {
        return $this->{'set'.$this->getMutatorMethodName($key).'Attribute'}($value);
    }

    /**
     * Get the value of an attribute using its mutator.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return mixed
     */
    protected function getMutatedAttributeValue($key, $value)
    {
        return $this->{'get'.$this->getMutatorMethodName($key).'Attribute'}($value);
    }

    /**
     * Get the mutator attribute method name.
     *
     * Hyphenated attributes will use pascal cased methods.
     *
     * @param string $key
     *
     * @return mixed
     */
    protected function getMutatorMethodName($key)
    {
        $key = ucwords(str_replace('-', ' ', $key));

        return str_replace(' ', '', $key);
    }

    /**
     * Sets the attributes property.
     *
     * Used when constructing an existing LDAP record.
     *
     * @param array $attributes
     *
     * @return $this
     */
    public function setRawAttributes(array $attributes = [])
    {
        // We will filter out those annoying 'count' keys returned
        // with LDAP results and lowercase all root array
        // keys to prevent any casing issues.
        $raw = array_change_key_case($this->filterRawAttributes($attributes), CASE_LOWER);

        // Before setting the models attributes, we'll filter out the
        // attributes that contain an integer key. LDAP results
        // will have contain have keys that contain the
        // attribute names. We don't need these.
        $this->attributes = array_filter($raw, function ($key) {
            return !is_int($key);
        }, ARRAY_FILTER_USE_KEY);

        // We will pull out the distinguished name from our raw attributes
        // and set it into our attributes array with the full attribute
        // definition. This allows us to normalize distinguished
        // names across different LDAP variants.
        if (array_key_exists('dn', $attributes)) {
            // In some LDAP instances the distinguished name may
            // be returned as an array. We will pull the
            // first value in this case.
            $this->dn = is_array($attributes['dn']) ?
                reset($attributes['dn']) :
                $attributes['dn'];
        }

        $this->syncOriginal();

        // Here we will set the exists attribute to true since
        // raw attributes are only set in the case of
        // attributes being loaded by query results.
        $this->exists = true;

        return $this;
    }

    /**
     * Filters the count key recursively from raw LDAP attributes.
     *
     * @param array $attributes
     * @param array $keys
     *
     * @return array
     */
    public function filterRawAttributes(array $attributes = [], array $keys = ['count', 'dn'])
    {
        foreach ($keys as $key) {
            unset($attributes[$key]);
        }

        foreach ($attributes as $key => $value) {
            $attributes[$key] = is_array($value) ?
                $this->filterRawAttributes($value, $keys) :
                $value;
        }

        return $attributes;
    }

    /**
     * Determine if the model has the given attribute.
     *
     * @param int|string $key
     *
     * @return bool
     */
    public function hasAttribute($key)
    {
        return array_key_exists($this->normalizeAttributeKey($key), $this->getNormalizedAttributes());
    }

    /**
     * Returns the number of attributes.
     *
     * @return int
     */
    public function countAttributes()
    {
        return count($this->getAttributes());
    }

    /**
     * Returns the models original attributes.
     *
     * @return array
     */
    public function getOriginal()
    {
        return $this->original;
    }

    /**
     * Get the attributes that have been changed since last sync.
     *
     * @return array
     */
    public function getDirty()
    {
        $dirty = [];

        foreach ($this->attributes as $key => $value) {
            if ($this->isDirty($key)) {
                // We need to reset the array using array_values due to
                // LDAP requiring consecutive indices (0, 1, 2 etc.).
                $dirty[$key] = array_values($value);
            }
        }

        return $dirty;
    }

    /**
     * Determine if the given attribute is dirty.
     *
     * @param string $key
     *
     * @return bool
     */
    public function isDirty($key)
    {
        return !$this->originalIsEquivalent($key);
    }

    /**
     * Convert the given date value to an LDAP compatible value.
     *
     * @param string $type
     * @param mixed  $value
     *
     * @throws LdapRecordException
     *
     * @return float|string
     */
    public function fromDateTime($type, $value)
    {
        return (new Timestamp($type))->fromDateTime($value);
    }

    /**
     * Convert the given LDAP date value to a Carbon instance.
     *
     * @param string $type
     * @param mixed  $value
     *
     * @throws LdapRecordException
     *
     * @return Carbon|null
     */
    public function asDateTime($type, $value)
    {
        return (new Timestamp($type))->toDateTime($value);
    }

    /**
     * Returns a normalized attribute key.
     *
     * @param string $key
     *
     * @return string
     */
    public function normalizeAttributeKey($key)
    {
        // Since LDAP supports hyphens in attribute names,
        // we'll convert attributes being retrieved by
        // underscores into hyphens for convenience.
        return strtolower(
            str_replace('_', '-', $key)
        );
    }

    /**
     * Determine if the new and old values for a given key are equivalent.
     *
     * @param string $key
     *
     * @return bool
     */
    protected function originalIsEquivalent($key)
    {
        if (!array_key_exists($key, $this->original)) {
            return false;
        }

        $current = $this->attributes[$key];
        $original = $this->original[$key];

        if ($current === $original) {
            return true;
        }

        return  is_numeric($current) &&
                is_numeric($original) &&
                strcmp((string) $current, (string) $original) === 0;
    }
}
