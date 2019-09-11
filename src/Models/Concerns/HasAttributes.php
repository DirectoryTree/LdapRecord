<?php

namespace LdapRecord\Models\Concerns;

use DateTime;
use Carbon\Carbon;
use DateTimeInterface;
use LdapRecord\Utilities;
use Carbon\CarbonInterface;
use Tightenco\Collect\Support\Arr;
use LdapRecord\LdapRecordException;

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
     * The format to use when converting dates to strings.
     *
     * @var string
     */
    protected $dateFormat = 'Y-m-d H:i:s';

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

        if ($this->hasAttribute($key) && $this->hasGetMutator($key)) {
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
    protected function isDateAttribute($key)
    {
        return array_key_exists($key, $this->getDates());
    }

    /**
     * Get the attributes that should be mutated to dates.
     *
     * @return array
     */
    protected function getDates()
    {
        // Since array string keys can be unique depending on casing differences,
        // we need to normalize the array key case so they are merged properly.
        $default = array_change_key_case($this->defaultDates, CASE_LOWER);
        $dates = array_change_key_case($this->dates, CASE_LOWER);

        return array_merge($default, $dates);
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
        } elseif ($value && $this->isDateAttribute($key)) {
            $value = $this->fromDateTime($this->getDates()[$key], $value);
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
     * LDAP attributes that contain hyphens
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
            return ! is_int($key);
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
    protected function fromDateTime($type, $value)
    {
        // If the value is numeric, we will assume it's a UNIX timestamp.
        if (is_numeric($value)) {
            $value = Carbon::createFromTimestamp($value);
        }
        // If a string is given, we will pass it into a new carbon instance.
        elseif (is_string($value)) {
            $value = new Carbon($value);
        }
        // If a date object is given, we will convert it to a carbon instance.
        elseif ($value instanceof DateTimeInterface) {
            $value = Carbon::instance($value);
        }

        // Here we'll set the dates time zone to UTC. LDAP uses UTC
        // as its timezone for all dates. We will also set the
        // microseconds to 0 as LDAP does not support them.
        $value->setTimezone('UTC')->micro(0);

        switch ($type) {
            case 'ldap':
                $value = $this->convertDateTimeToLdapTime($value);
                break;
            case 'windows':
                $value = $this->convertDateTimeToWindows($value);
                break;
            case 'windows-int':
                $value = $this->convertDateTimeToWindowsInteger($value);
                break;
            default:
                throw new LdapRecordException("Unrecognized date type '{$type}'");
        }

        return $value;
    }

    /**
     * Convert the given LDAP date value to a Carbon instance.
     *
     * @param string $type
     * @param mixed  $value
     *
     * @throws LdapRecordException
     *
     * @return Carbon
     */
    protected function asDateTime($type, $value)
    {
        if ($value instanceof CarbonInterface) {
            return $value;
        }

        switch ($type) {
            case 'ldap':
                $value = $this->convertLdapTimeToDateTime($value);
                break;
            case 'windows':
                $value = $this->convertWindowsTimeToDateTime($value);
                break;
            case 'windows-int':
                $value = $this->convertWindowsIntegerTimeToDateTime($value);
                break;
            default:
                throw new LdapRecordException("Unrecognized date type '{$type}'");
        }

        if ($value instanceof DateTimeInterface) {
            return (new Carbon())->setDateTimeFrom($value);
        }
    }

    /**
     * Converts standard LDAP timestamps to a date time object.
     *
     * @param string $value
     *
     * @return DateTime|bool
     */
    protected function convertLdapTimeToDateTime($value)
    {
        return DateTime::createFromFormat('YmdHisZ', $value);
    }

    /**
     * Converts date objects to a standard LDAP timestamp.
     *
     * @param DateTimeInterface $date
     *
     * @return string
     */
    protected function convertDateTimeToLdapTime(DateTimeInterface $date)
    {
        return $date->format('YmdHis\Z');
    }

    /**
     * Converts standard windows timestamps to a date time object.
     *
     * @param string $value
     *
     * @return DateTime|bool
     */
    protected function convertWindowsTimeToDateTime($value)
    {
        return DateTime::createFromFormat('YmdHis.0Z', $value);
    }

    /**
     * Converts date objects to a windows timestamp.
     *
     * @param DateTimeInterface $date
     *
     * @return string
     */
    protected function convertDateTimeToWindows(DateTimeInterface $date)
    {
        return $date->format('YmdHis.0\Z');
    }

    /**
     * Converts standard windows integer dates to a date time object.
     *
     * @param int $value
     *
     * @throws \Exception
     *
     * @return DateTime|null
     */
    protected function convertWindowsIntegerTimeToDateTime($value)
    {
        // ActiveDirectory dates that contain integers may return
        // "0" when they are not set. We will validate that here.
        return $value ? (new DateTime())->setTimestamp(
            Utilities::convertWindowsTimeToUnixTime($value)
        ) : null;
    }

    /**
     * Converts date objects to a windows integer timestamp.
     *
     * @param DateTimeInterface $date
     *
     * @return float
     */
    protected function convertDateTimeToWindowsInteger(DateTimeInterface $date)
    {
        return Utilities::convertUnixTimeToWindowsTime($date->getTimestamp());
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
