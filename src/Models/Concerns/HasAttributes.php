<?php

namespace LdapRecord\Models\Concerns;

use Tightenco\Collect\Support\Arr;

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

        return $this->getAttributeValue(
            $this->normalizeAttributeKey($key)
        );
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
        $value = $this->getAttributeFromArray($key);

        if ($this->hasGetMutator($key)) {
            return $this->getMutatedAttributeValue($key, $value);
        }

        return $value;
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
        return $this->attributes[$key] ?? null;
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
        // Normalize key.
        $key = $this->normalizeAttributeKey($key);

        if ($this->hasSetMutator($key)) {
            return $this->setMutatedAttributeValue($key, $value);
        }

        // Due to LDAP's multi-valued nature, we must always wrap given values
        // inside of arrays, otherwise we will receive exceptions saving.
        $this->attributes[$key] = is_array($value) ? $value : [$value];

        return $this;
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
        return $this->setAttribute($key, array_merge(
            $this->getAttribute($key) ?? [], [$value]
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
        return method_exists($this, 'get'.$key.'Attribute');
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
        return method_exists($this, 'set'.$key.'Attribute');
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
        return $this->{'set'.$key.'Attribute'}($value);
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
        return $this->{'get'.$key.'Attribute'}($value);
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
        $this->attributes = array_change_key_case($this->filterRawAttributes($attributes), CASE_LOWER);

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
        return array_key_exists($this->normalizeAttributeKey($key), $this->attributes);
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
     * Returns a normalized attribute key.
     *
     * @param string $key
     *
     * @return string
     */
    protected function normalizeAttributeKey($key)
    {
        return strtolower($key);
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
