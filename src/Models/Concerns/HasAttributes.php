<?php

namespace LdapRecord\Models\Concerns;

use Carbon\Carbon;
use DateTimeInterface;
use Tightenco\Collect\Support\Arr;
use LdapRecord\LdapRecordException;
use LdapRecord\Models\Attributes\MbString;
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
     * The format that dates must be output to for serialization.
     *
     * @var string
     */
    protected $dateFormat;

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
     * The cache of the mutated attributes for each class.
     *
     * @var array
     */
    protected static $mutatorCache = [];

    /**
     * Convert the model's attributes to an array.
     *
     * @return array
     */
    public function attributesToArray()
    {
        // Here we will replace our LDAP formatted dates with
        // properly formatted ones, so dates do not need to
        // be converted manually after being returned.
        $attributes = $this->addDateAttributesToArray(
            $attributes = $this->getArrayableAttributes()
        );

        $attributes = $this->addMutatedAttributesToArray(
            $attributes,
            $mutatedAttributes = $this->getMutatedAttributes()
        );

        // Before we go ahead and encode each value, we'll attempt
        // converting any necessary attribute values to ensure
        // they can be encoded, such as GUIDs and SIDs.
        $attributes = $this->convertAttributesForJson($attributes);

        array_walk_recursive($attributes, function (&$value) {
            $value = $this->encodeValue($value);
        });

        return $attributes;
    }

    /**
     * Add the date attributes to the attributes array.
     *
     * @param array $attributes
     *
     * @return array
     */
    protected function addDateAttributesToArray(array $attributes)
    {
        foreach ($this->getDates() as $attribute => $type) {
            if (!isset($attributes[$attribute])) {
                continue;
            }

            $date = $this->asDateTime($type, $attributes[$attribute]);

            $attributes[$attribute] = $date instanceof Carbon
                ? Arr::wrap($this->serializeDate($date))
                : $attributes[$attribute];
        }

        return $attributes;
    }

    /**
     * Prepare a date for array / JSON serialization.
     *
     * @param DateTimeInterface $date
     *
     * @return string
     */
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format($this->getDateFormat());
    }

    /**
     * Encode the given value for proper serialization.
     *
     * @param string $value
     *
     * @return string
     */
    protected function encodeValue($value)
    {
        // If we are able to detect the encoding, we will
        // encode only the attributes that need to be,
        // so that we do not double encode values.
        return MbString::isLoaded() && MbString::isUtf8($value) ? $value : utf8_encode($value);
    }

    /**
     * Add the mutated attributes to the attributes array.
     *
     * @param array $attributes
     * @param array $mutatedAttributes
     *
     * @return array
     */
    protected function addMutatedAttributesToArray(array $attributes, array $mutatedAttributes)
    {
        foreach ($mutatedAttributes as $key) {
            // We want to spin through all the mutated attributes for this model and call
            // the mutator for the attribute. We cache off every mutated attributes so
            // we don't have to constantly check on attributes that actually change.
            if (!array_key_exists($key, $attributes)) {
                continue;
            }

            // Next, we will call the mutator for this attribute so that we can get these
            // mutated attribute's actual values. After we finish mutating each of the
            // attributes we will return this final array of the mutated attributes.
            $attributes[$key] = $this->mutateAttributeForArray(
                $key,
                $attributes[$key]
            );
        }

        return $attributes;
    }

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
     * Get an attribute array of all arrayable attributes.
     *
     * @return array
     */
    protected function getArrayableAttributes()
    {
        return $this->getArrayableItems($this->attributes);
    }

    /**
     * Get an attribute array of all arrayable values.
     *
     * @param array $values
     *
     * @return array
     */
    protected function getArrayableItems(array $values)
    {
        if (count($visible = $this->getVisible()) > 0) {
            $values = array_intersect_key($values, array_flip($visible));
        }

        if (count($hidden = $this->getHidden()) > 0) {
            $values = array_diff_key($values, array_flip($hidden));
        }

        return $values;
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
     * Get the format for date serialization.
     *
     * @return string
     */
    public function getDateFormat()
    {
        return $this->dateFormat ?: DateTimeInterface::ISO8601;
    }

    /**
     * Set the date format used by the model for serialization.
     *
     * @param string $format
     *
     * @return $this
     */
    public function setDateFormat($format)
    {
        $this->dateFormat = $format;

        return $this;
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
     * Get the value of an attribute using its mutator for array conversion.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return array
     */
    protected function mutateAttributeForArray($key, $value)
    {
        return Arr::wrap(
            $this->getMutatedAttributeValue($key, $value)
        );
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
            $this->dn = is_array($attributes['dn'])
                ? reset($attributes['dn'])
                : $attributes['dn'];
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
            $attributes[$key] = is_array($value)
                ? $this->filterRawAttributes($value, $keys)
                : $value;
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
     * @return Carbon|false
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

    /**
     * Get the mutated attributes for a given instance.
     *
     * @return array
     */
    public function getMutatedAttributes()
    {
        $class = static::class;

        if (!isset(static::$mutatorCache[$class])) {
            static::cacheMutatedAttributes($class);
        }

        return static::$mutatorCache[$class];
    }

    /**
     * Extract and cache all the mutated attributes of a class.
     *
     * @param string $class
     *
     * @return void
     */
    public static function cacheMutatedAttributes($class)
    {
        static::$mutatorCache[$class] = collect(static::getMutatorMethods($class))->reject(function ($match) {
            return $match === 'First';
        })->map(function ($match) {
            return lcfirst($match);
        })->all();
    }

    /**
     * Get all of the attribute mutator methods.
     *
     * @param mixed $class
     *
     * @return array
     */
    protected static function getMutatorMethods($class)
    {
        preg_match_all('/(?<=^|;)get([^;]+?)Attribute(;|$)/', implode(';', get_class_methods($class)), $matches);

        return $matches[1];
    }
}
