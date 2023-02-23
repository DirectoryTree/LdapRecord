<?php

namespace LdapRecord\Models\Concerns;

use Carbon\Carbon;
use DateTimeInterface;
use Exception;
use LdapRecord\LdapRecordException;
use LdapRecord\Models\Attributes\MbString;
use LdapRecord\Models\Attributes\Timestamp;
use LdapRecord\Models\DetectsResetIntegers;
use LdapRecord\Support\Arr;

trait HasAttributes
{
    use DetectsResetIntegers;

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
     * The attributes that should be cast to their native types.
     *
     * @var array
     */
    protected $casts = [];

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [];

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
     * Convert the model's original attributes to an array.
     *
     * @return array
     */
    public function originalToArray()
    {
        return $this->encodeAttributes(
            $this->convertAttributesForJson($this->original)
        );
    }

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
            $this->getMutatedAttributes()
        );

        // Before we go ahead and encode each value, we'll attempt
        // converting any necessary attribute values to ensure
        // they can be encoded, such as GUIDs and SIDs.
        $attributes = $this->convertAttributesForJson($attributes);

        // Here we will grab all of the appended, calculated attributes to this model
        // as these attributes are not really in the attributes array, but are run
        // when we need to array or JSON the model for convenience to the coder.
        foreach ($this->getArrayableAppends() as $key) {
            $attributes[$key] = $this->mutateAttributeForArray($key, null);
        }

        // Now we will go through each attribute to make sure it is
        // properly encoded. If attributes aren't in UTF-8, we will
        // encounter JSON encoding errors upon model serialization.
        return $this->encodeAttributes($attributes);
    }

    /**
     * Convert the model's serialized original attributes to their original form.
     *
     * @return array
     */
    public function arrayToOriginal(array $attributes)
    {
        return $this->decodeAttributes(
            $this->convertAttributesFromJson($attributes)
        );
    }

    /**
     * Convert the model's serialized attributes to their original form.
     *
     * @return array
     */
    public function arrayToAttributes(array $attributes)
    {
        $attributes = $this->restoreDateAttributesFromArray($attributes);

        return $this->decodeAttributes(
            $this->convertAttributesFromJson($attributes)
        );
    }

    /**
     * Add the date attributes to the attributes array.
     *
     * @return array
     */
    protected function addDateAttributesToArray(array $attributes)
    {
        foreach ($this->getDates() as $attribute => $type) {
            if (! isset($attributes[$attribute])) {
                continue;
            }

            $date = $this->asDateTime($attributes[$attribute], $type);

            $attributes[$attribute] = $date instanceof Carbon
                ? Arr::wrap($this->serializeDate($date))
                : $attributes[$attribute];
        }

        return $attributes;
    }

    /**
     * Restore the date attributes to their true value from serialized attributes.
     *
     * @return array
     */
    protected function restoreDateAttributesFromArray(array $attributes)
    {
        foreach ($this->getDates() as $attribute => $type) {
            if (! isset($attributes[$attribute])) {
                continue;
            }

            $date = $this->fromDateTime($attributes[$attribute], $type);

            $attributes[$attribute] = Arr::wrap($date);
        }

        return $attributes;
    }

    /**
     * Prepare a date for array / JSON serialization.
     *
     * @return string
     */
    protected function serializeDate(DateTimeInterface $date)
    {
        return $date->format($this->getDateFormat());
    }

    /**
     * Recursively UTF-8 encode the given attributes.
     *
     * @return array
     */
    protected function encodeAttributes($attributes)
    {
        array_walk_recursive($attributes, function (&$value) {
            $value = $this->encodeValue($value);
        });

        return $attributes;
    }

    /**
     * Recursively UTF-8 decode the given attributes.
     *
     * @param  array  $attributes
     * @return array
     */
    public function decodeAttributes($attributes)
    {
        array_walk_recursive($attributes, function (&$value) {
            $value = $this->decodeValue($value);
        });

        return $attributes;
    }

    /**
     * Encode the value for serialization.
     *
     * @param  string  $value
     * @return string
     */
    protected function encodeValue($value)
    {
        // If we are able to detect the encoding, we will
        // encode only the attributes that need to be,
        // so that we do not double encode values.
        if (MbString::isLoaded() && MbString::isUtf8($value)) {
            return $value;
        }

        return utf8_encode($value);
    }

    /**
     * Decode the value from serialization.
     *
     * @param  string  $value
     * @return string
     */
    protected function decodeValue($value)
    {
        if (MbString::isLoaded() && MbString::isUtf8($value)) {
            return mb_convert_encoding($value, 'UTF-8', 'ISO-8859-1');
        }

        return $value;
    }

    /**
     * Add the mutated attributes to the attributes array.
     *
     * @return array
     */
    protected function addMutatedAttributesToArray(array $attributes, array $mutatedAttributes)
    {
        foreach ($mutatedAttributes as $key) {
            // We want to spin through all the mutated attributes for this model and call
            // the mutator for the attribute. We cache off every mutated attributes so
            // we don't have to constantly check on attributes that actually change.
            if (! Arr::exists($attributes, $key)) {
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
     * @param  int|string  $key
     */
    public function getAttribute($key, $default = null)
    {
        if (! $key) {
            return;
        }

        return $this->getAttributeValue($key, $default);
    }

    /**
     * Get an attribute's value.
     *
     * @param  string  $key
     */
    public function getAttributeValue($key, $default = null)
    {
        $key = $this->normalizeAttributeKey($key);
        $value = $this->getAttributeFromArray($key);

        if ($this->hasGetMutator($key)) {
            return $this->getMutatedAttributeValue($key, $value);
        }

        if ($this->isDateAttribute($key) && ! is_null($value)) {
            return $this->asDateTime(Arr::first($value), $this->getDates()[$key]);
        }

        if ($this->isCastedAttribute($key) && ! is_null($value)) {
            return $this->castAttribute($key, $value);
        }

        return is_null($value) ? $default : $value;
    }

    /**
     * Get the model's raw attribute value.
     *
     * @param  string  $key
     */
    public function getRawAttribute($key, $default = null)
    {
        return Arr::get($this->attributes, $key, $default);
    }

    /**
     * Determine if the given attribute is a date.
     *
     * @param  string  $key
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
        // Since array string keys can be unique depending
        // on casing differences, we need to normalize the
        // array key case so they are merged properly.
        return array_merge(
            array_change_key_case($this->defaultDates, CASE_LOWER),
            array_change_key_case($this->dates, CASE_LOWER)
        );
    }

    /**
     * Convert the given date value to an LDAP compatible value.
     *
     * @param  string  $type
     * @return float|string
     *
     * @throws LdapRecordException
     */
    public function fromDateTime($value, $type)
    {
        return (new Timestamp($type))->fromDateTime($value);
    }

    /**
     * Convert the given LDAP date value to a Carbon instance.
     *
     * @param  string  $type
     * @return Carbon|false
     *
     * @throws LdapRecordException
     */
    public function asDateTime($value, $type)
    {
        return (new Timestamp($type))->toDateTime($value);
    }

    /**
     * Determine whether an attribute should be cast to a native type.
     *
     * @param  string  $key
     * @param  array|string|null  $types
     * @return bool
     */
    public function hasCast($key, $types = null)
    {
        if (array_key_exists($key, $this->getCasts())) {
            return $types ? in_array($this->getCastType($key), (array) $types, true) : true;
        }

        return false;
    }

    /**
     * Get the attributes that should be cast to their native types.
     *
     * @return array
     */
    protected function getCasts()
    {
        return array_change_key_case($this->casts, CASE_LOWER);
    }

    /**
     * Determine whether a value is JSON castable for inbound manipulation.
     *
     * @param  string  $key
     * @return bool
     */
    protected function isJsonCastable($key)
    {
        return $this->hasCast($key, ['array', 'json', 'object', 'collection']);
    }

    /**
     * Get the type of cast for a model attribute.
     *
     * @param  string  $key
     * @return string
     */
    protected function getCastType($key)
    {
        if ($this->isDecimalCast($this->getCasts()[$key])) {
            return 'decimal';
        }

        if ($this->isDateTimeCast($this->getCasts()[$key])) {
            return 'datetime';
        }

        return trim(strtolower($this->getCasts()[$key]));
    }

    /**
     * Determine if the cast is a decimal.
     *
     * @param  string  $cast
     * @return bool
     */
    protected function isDecimalCast($cast)
    {
        return strncmp($cast, 'decimal:', 8) === 0;
    }

    /**
     * Determine if the cast is a datetime.
     *
     * @param  string  $cast
     * @return bool
     */
    protected function isDateTimeCast($cast)
    {
        return strncmp($cast, 'datetime:', 8) === 0;
    }

    /**
     * Determine if the given attribute must be casted.
     *
     * @param  string  $key
     * @return bool
     */
    protected function isCastedAttribute($key)
    {
        return array_key_exists($key, array_change_key_case($this->casts, CASE_LOWER));
    }

    /**
     * Cast an attribute to a native PHP type.
     *
     * @param  string  $key
     * @param  array|null  $value
     */
    protected function castAttribute($key, $value)
    {
        $value = $this->castRequiresArrayValue($key) ? $value : Arr::first($value);

        if (is_null($value)) {
            return $value;
        }

        switch ($this->getCastType($key)) {
            case 'int':
            case 'integer':
                return (int) $value;
            case 'real':
            case 'float':
            case 'double':
                return $this->fromFloat($value);
            case 'decimal':
                return $this->asDecimal($value, explode(':', $this->getCasts()[$key], 2)[1]);
            case 'string':
                return (string) $value;
            case 'bool':
            case 'boolean':
                return $this->asBoolean($value);
            case 'object':
                return $this->fromJson($value, $asObject = true);
            case 'array':
            case 'json':
                return $this->fromJson($value);
            case 'collection':
                return $this->newCollection($value);
            case 'datetime':
                return $this->asDateTime($value, explode(':', $this->getCasts()[$key], 2)[1]);
            default:
                return $value;
        }
    }

    /**
     * Determine if the cast type requires the first attribute value.
     *
     * @return bool
     */
    protected function castRequiresArrayValue($key)
    {
        return in_array($this->getCastType($key), ['collection']);
    }

    /**
     * Cast the given attribute to JSON.
     *
     * @param  string  $key
     * @return string
     */
    protected function castAttributeAsJson($key, $value)
    {
        $value = $this->asJson($value);

        if ($value === false) {
            $class = get_class($this);
            $message = json_last_error_msg();

            throw new Exception("Unable to encode attribute [{$key}] for model [{$class}] to JSON: {$message}.");
        }

        return $value;
    }

    /**
     * Convert the model to its JSON representation.
     *
     * @return string
     */
    public function toJson()
    {
        return json_encode($this);
    }

    /**
     * Encode the given value as JSON.
     *
     * @return string
     */
    protected function asJson($value)
    {
        return json_encode($value);
    }

    /**
     * Decode the given JSON back into an array or object.
     *
     * @param  string  $value
     * @param  bool  $asObject
     */
    public function fromJson($value, $asObject = false)
    {
        return json_decode($value, ! $asObject);
    }

    /**
     * Decode the given float.
     */
    public function fromFloat($value)
    {
        return match ((string) $value) {
            'NaN' => NAN,
            'Infinity' => INF,
            '-Infinity' => -INF,
            default => (float) $value,
        };
    }

    /**
     * Cast the value to a boolean.
     *
     * @return bool
     */
    protected function asBoolean($value)
    {
        $map = ['true' => true, 'false' => false];

        return $map[strtolower($value)] ?? (bool) $value;
    }

    /**
     * Cast a decimal value as a string.
     *
     * @param  float  $value
     * @param  int  $decimals
     * @return string
     */
    protected function asDecimal($value, $decimals)
    {
        return number_format($value, $decimals, '.', '');
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
     * Get all of the appendable values that are arrayable.
     *
     * @return array
     */
    protected function getArrayableAppends()
    {
        if (empty($this->appends)) {
            return [];
        }

        return $this->getArrayableItems(
            array_combine($this->appends, $this->appends)
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
     * @param  string  $format
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
     * @param  string  $key
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
     * @param  string  $key
     */
    public function getFirstAttribute($key, $default = null)
    {
        return Arr::first(
            Arr::wrap($this->getAttribute($key, $default)),
        );
    }

    /**
     * Returns all the model's attributes.
     *
     * @return array
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * Set an attribute value by the specified key.
     *
     * @param  string  $key
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
            ! $this->valueIsResetInteger($value)
        ) {
            $value = $this->fromDateTime($value, $this->getDates()[$key]);
        }

        if ($this->isJsonCastable($key) && ! is_null($value)) {
            $value = $this->castAttributeAsJson($key, $value);
        }

        $this->attributes[$key] = Arr::wrap($value);

        return $this;
    }

    /**
     * Set an attribute on the model. No checking is done.
     *
     * @param  string  $key
     * @return $this
     */
    public function setRawAttribute($key, $value)
    {
        $key = $this->normalizeAttributeKey($key);

        $this->attributes[$key] = Arr::wrap($value);

        return $this;
    }

    /**
     * Set the models first attribute value.
     *
     * @param  string  $key
     * @return $this
     */
    public function setFirstAttribute($key, $value)
    {
        return $this->setAttribute($key, Arr::wrap($value));
    }

    /**
     * Add a unique value to the given attribute.
     *
     * @param  string  $key
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
     * @param  string  $key
     * @return bool
     */
    public function hasGetMutator($key)
    {
        return method_exists($this, 'get'.$this->getMutatorMethodName($key).'Attribute');
    }

    /**
     * Determine if a set mutator exists for an attribute.
     *
     * @param  string  $key
     * @return bool
     */
    public function hasSetMutator($key)
    {
        return method_exists($this, 'set'.$this->getMutatorMethodName($key).'Attribute');
    }

    /**
     * Set the value of an attribute using its mutator.
     *
     * @param  string  $key
     */
    protected function setMutatedAttributeValue($key, $value)
    {
        return $this->{'set'.$this->getMutatorMethodName($key).'Attribute'}($value);
    }

    /**
     * Get the value of an attribute using its mutator.
     *
     * @param  string  $key
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
     * @param  string  $key
     */
    protected function getMutatorMethodName($key)
    {
        $key = ucwords(str_replace('-', ' ', $key));

        return str_replace(' ', '', $key);
    }

    /**
     * Get the value of an attribute using its mutator for array conversion.
     *
     * @param  string  $key
     * @return array
     */
    protected function mutateAttributeForArray($key, $value)
    {
        return Arr::wrap(
            $this->getMutatedAttributeValue($key, $value)
        );
    }

    /**
     * Set the attributes property.
     *
     * Used when constructing an existing LDAP record.
     *
     * @return $this
     */
    public function setRawAttributes(array $attributes = [])
    {
        // We will filter out those annoying 'count' keys
        // returned with LDAP results and lowercase all
        // root array keys to prevent any casing issues.
        $raw = array_change_key_case($this->filterRawAttributes($attributes), CASE_LOWER);

        // Before setting the models attributes, we will filter
        // out the attributes that contain an integer key. LDAP
        // search results will contain integer keys that have
        // attribute names as values. We don't need these.
        $this->attributes = array_filter($raw, function ($key) {
            return ! is_int($key);
        }, ARRAY_FILTER_USE_KEY);

        // LDAP search results will contain the distinguished
        // name inside of the `dn` key. We will retrieve this,
        // and then set it on the model for accessibility.
        if (Arr::exists($attributes, 'dn')) {
            $this->dn = Arr::accessible($attributes['dn'])
                ? Arr::first($attributes['dn'])
                : $attributes['dn'];
        }

        $this->syncOriginal();

        // Here we will set the exists attribute to true,
        // since raw attributes are only set in the case
        // of attributes being loaded by query results.
        $this->exists = true;

        return $this;
    }

    /**
     * Filters the count key recursively from raw LDAP attributes.
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
     * @param  int|string  $key
     * @return bool
     */
    public function hasAttribute($key)
    {
        return [] !== ($this->attributes[$this->normalizeAttributeKey($key)] ?? []);
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
     * Get the model's original attributes.
     *
     * @return array
     */
    public function getOriginal()
    {
        return $this->original;
    }

    /**
     * Get the model's raw original attribute values.
     *
     * @param  string  $key
     */
    public function getRawOriginal($key, $default = null)
    {
        return Arr::get($this->original, $key, $default);
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
                // We would receive an exception otherwise.
                $dirty[$key] = array_values($value);
            }
        }

        return $dirty;
    }

    /**
     * Determine if the given attribute is dirty.
     *
     * @param  string  $key
     * @return bool
     */
    public function isDirty($key)
    {
        return ! $this->originalIsEquivalent($key);
    }

    /**
     * Get the accessors being appended to the models array form.
     *
     * @return array
     */
    public function getAppends()
    {
        return $this->appends;
    }

    /**
     * Set the accessors to append to model arrays.
     *
     * @return $this
     */
    public function setAppends(array $appends)
    {
        $this->appends = $appends;

        return $this;
    }

    /**
     * Return whether the accessor attribute has been appended.
     *
     * @param  string  $attribute
     * @return bool
     */
    public function hasAppended($attribute)
    {
        return in_array($attribute, $this->appends);
    }

    /**
     * Returns a normalized attribute key.
     *
     * @param  string  $key
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
     * @param  string  $key
     * @return bool
     */
    protected function originalIsEquivalent($key)
    {
        if (! array_key_exists($key, $this->original)) {
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

        if (! isset(static::$mutatorCache[$class])) {
            static::cacheMutatedAttributes($class);
        }

        return static::$mutatorCache[$class];
    }

    /**
     * Extract and cache all the mutated attributes of a class.
     *
     * @param  string  $class
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
     * @return array
     */
    protected static function getMutatorMethods($class)
    {
        preg_match_all('/(?<=^|;)get([^;]+?)Attribute(;|$)/', implode(';', get_class_methods($class)), $matches);

        return $matches[1];
    }
}
