<?php

namespace LdapRecord\Models;

use ArrayAccess;
use JsonSerializable;
use InvalidArgumentException;
use UnexpectedValueException;
use Tightenco\Collect\Support\Arr;
use LdapRecord\Utilities;
use LdapRecord\Query\Builder;
use LdapRecord\Query\Collection;
use LdapRecord\Connections\Container;
use LdapRecord\Connections\LdapInterface;
use LdapRecord\Models\Attributes\Guid;
use LdapRecord\Models\Attributes\MbString;
use LdapRecord\Connections\ConnectionException;
use LdapRecord\Models\Attributes\DistinguishedName;

/** @mixin Builder */
abstract class Model implements ArrayAccess, JsonSerializable
{
    use Concerns\HasEvents,
        Concerns\HasAttributes,
        Concerns\HasRelationships;

    /**
     * Indicates if the model exists.
     *
     * @var bool
     */
    public $exists = false;

    /**
     * The models distinguished name.
     *
     * @var string|null
     */
    protected $dn;

    /**
     * The object classes of the LDAP model.
     * 
     * @var array
     */
    public static $objectClasses = [];

    /**
     * The current LDAP connection to utilize.
     *
     * @var string
     */
    protected $connection = 'default';
    
    /**
     * The attribute key that contains the Object GUID.
     * 
     * @var string
     */
    protected $guidKey = 'objectguid';

    /**
     * Contains the models modifications.
     *
     * @var array
     */
    protected $modifications = [];

    /**
     * Constructor.
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    /**
     * Handle dynamic method calls into the model.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        if (method_exists($this, $method)) {
            return $this->$method(...$parameters);
        }

        return $this->newQuery()->$method(...$parameters);
    }

    /**
     * Handle dynamic static method calls into the method.
     *
     * @param string $method
     * @param array  $parameters
     *
     * @return mixed
     */
    public static function __callStatic($method, $parameters)
    {
        return (new static)->$method(...$parameters);
    }

    /**
     * Returns the models distinguished name.
     *
     * @return string|null
     */
    public function getDn()
    {
        return $this->dn;
    }

    /**
     * Set the models distinguished name.
     *
     * @param string $dn
     *
     * @return static
     */
    public function setDn($dn)
    {
        $this->dn = $dn;

        return $this;
    }

    /**
     * Get the LDAP connection for the model.
     *
     * @return \LdapRecord\Connections\Connection
     */
    public function getConnection()
    {
        return static::resolveConnection($this->connection);
    }

    /**
     * Get the current connection name for the model.
     *
     * @return string
     */
    public function getConnectionName()
    {
        return $this->connection;
    }

    /**
     * Set the connection associated with the model.
     *
     * @param string $name
     *
     * @return $this
     */
    public function setConnection($name)
    {
        $this->connection = $name;

        return $this;
    }

    /**
     * Begin querying the model on a given connection.
     *
     * @param string|null $connection
     *
     * @return Builder
     */
    public static function on($connection = null)
    {
        $instance = new static;

        $instance->setConnection($connection);

        return $instance->newQuery();
    }

    /**
     * Begin querying the LDAP model.
     *
     * @return Builder
     */
    public static function query()
    {
        return (new static)->newQuery();
    }

    /**
     * Get a new query builder for the LDAP model.
     *
     * @return Builder
     */
    public function newQuery()
    {
        return $this->registerQueryScopes(
            $this->newQueryWithoutScopes()
        )->setModel($this);
    }

    /**
     * Get a new query builder that doesn't have any global scopes.
     *
     * @return Builder
     */
    public function newQueryWithoutScopes()
    {
        $connection = static::resolveConnection($this->connection);

        return $this->newQueryBuilder($connection->getLdapConnection())
            ->in($connection->getConfiguration()->get('base_dn'));
    }

    /**
     * Create a new query builder.
     *
     * @param LdapInterface $connection
     *
     * @return Builder
     */
    public function newQueryBuilder(LdapInterface $connection)
    {
        return new Builder($connection);
    }

    /**
     * Resolve a connection instance.
     *
     * @param string|null $connection
     *
     * @return \LdapRecord\Connections\Connection
     */
    public static function resolveConnection($connection = null)
    {
        return Container::getInstance()->get($connection);
    }

    /**
     * Register the query scopes for this builder instance.
     *
     * @param Builder $builder
     *
     * @return Builder
     */
    public function registerQueryScopes($builder)
    {
        $this->applyGlobalScopes($builder);

        return $builder;
    }

    /**
     * Apply the global scopes to the given builder instance.
     *
     * @param Builder $query
     *
     * @return void
     */
    public function applyGlobalScopes(Builder $query)
    {
        foreach (static::$objectClasses as $objectClass) {
            $query->where('objectclass', '=', $objectClass);
        }
    }

    /**
     * Returns the models distinguished name when the model is converted to a string.
     *
     * @return null|string
     */
    public function __toString()
    {
        return $this->getDn();
    }

    /**
     * Returns a new batch modification.
     *
     * @param string|null     $attribute
     * @param string|int|null $type
     * @param array           $values
     *
     * @return BatchModification
     */
    public function newBatchModification($attribute = null, $type = null, $values = [])
    {
        return new BatchModification($attribute, $type, $values);
    }

    /**
     * Returns a new collection with the specified items.
     *
     * @param mixed $items
     *
     * @return Collection
     */
    public function newCollection($items = [])
    {
        return new Collection($items);
    }

    /**
     * Dynamically retrieve attributes on the object.
     *
     * @param mixed $key
     *
     * @return bool
     */
    public function __get($key)
    {
        return $this->getAttribute($key);
    }

    /**
     * Dynamically set attributes on the object.
     *
     * @param mixed $key
     * @param mixed $value
     *
     * @return $this
     */
    public function __set($key, $value)
    {
        return $this->setAttribute($key, $value);
    }

    /**
     * Determine if the given offset exists.
     *
     * @param string $offset
     *
     * @return bool
     */
    public function offsetExists($offset)
    {
        return ! is_null($this->getAttribute($offset));
    }

    /**
     * Get the value for a given offset.
     *
     * @param string $offset
     *
     * @return mixed
     */
    public function offsetGet($offset)
    {
        return $this->getAttribute($offset);
    }

    /**
     * Set the value at the given offset.
     *
     * @param string $offset
     * @param mixed  $value
     *
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        $this->setAttribute($offset, $value);
    }

    /**
     * Unset the value at the given offset.
     *
     * @param string $offset
     *
     * @return void
     */
    public function offsetUnset($offset)
    {
        unset($this->attributes[$offset]);
    }

    /**
     * Determine if an attribute exists on the model.
     *
     * @param string $key
     *
     * @return bool
     */
    public function __isset($key)
    {
        return $this->offsetExists($key);
    }

    /**
     * Unset an attribute on the model.
     *
     * @param string $key
     *
     * @return void
     */
    public function __unset($key)
    {
        $this->offsetUnset($key);
    }

    /**
     * Convert the object into something JSON serializable.
     *
     * @return array
     */
    public function jsonSerialize()
    {
        $attributes = $this->getAttributes();

        array_walk_recursive($attributes, function (&$val) {
            if (MbString::isLoaded()) {
                // If we're able to detect the attribute
                // encoding, we'll encode only the
                // attributes that need to be.
                if (!MbString::isUtf8($val)) {
                    $val = utf8_encode($val);
                }
            } else {
                // If the mbstring extension is not loaded, we'll
                // encode all attributes to make sure
                // they are encoded properly.
                $val = utf8_encode($val);
            }
        });

        return $this->convertAttributesForJson($attributes);
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
        ]);
    }

    /**
     * Reload a fresh model instance from the directory.
     *
     * @return static|false
     */
    public function fresh()
    {
        if (! $this->exists) {
            return false;
        }

        return $this->query()->findByDn($this->getDn());
    }

    /**
     * Hydrates a new collection of models.
     *
     * @param array $records
     *
     * @return Collection
     */
    public function hydrate($records)
    {
        return $this->newCollection($records)->transform(function ($attributes) {
            return (new static)->setRawAttributes($attributes);
        });
    }

    /**
     * Converts the current model into the given model.
     * 
     * @param Model $into
     * 
     * @return Model
     */
    public function convert(Model $into)
    {
        $into->setRawAttributes($this->getAttributes());
        $into->setConnection($this->getConnectionName());

        return $into;
    }

    /**
     * Synchronizes the current models attributes with the directory values.
     *
     * @return bool
     */
    public function syncRaw()
    {
        if ($model = $this->fresh()) {
            $this->setRawAttributes($model->getAttributes());

            return true;
        }

        return false;
    }

    /**
     * Returns the models batch modifications to be processed.
     *
     * @return array
     */
    public function getModifications()
    {
        $this->buildModificationsFromDirty();

        return $this->modifications;
    }

    /**
     * Sets the models modifications array.
     *
     * @param array $modifications
     *
     * @return $this
     */
    public function setModifications(array $modifications = [])
    {
        $this->modifications = $modifications;

        return $this;
    }

    /**
     * Adds a batch modification to the models modifications array.
     *
     * @param array|BatchModification $mod
     *
     * @throws InvalidArgumentException
     *
     * @return $this
     */
    public function addModification($mod = [])
    {
        if ($mod instanceof BatchModification) {
            $mod = $mod->get();
        }

        if ($this->isValidModification($mod)) {
            $this->modifications[] = $mod;

            return $this;
        }

        throw new InvalidArgumentException(
            "The batch modification array does not include the mandatory 'attrib' or 'modtype' keys."
        );
    }

    /**
     * Returns the models guid key.
     *
     * @return string
     */
    public function getGuidKey()
    {
        return $this->guidKey;
    }

    /**
     * Returns the models ANR attributes when incompatible.
     *
     * @return array
     */
    public function getAnrAttributes()
    {
        return ['cn', 'sn', 'uid', 'name', 'mail', 'givenname', 'displayname'];
    }

    /**
     * Returns a new DistinguishedName object for building onto.
     *
     * @param string $baseDn
     *
     * @return DistinguishedName
     */
    public function getNewDnBuilder($baseDn = '')
    {
        return new DistinguishedName($baseDn);
    }

    /**
     * Returns the model's binary object GUID.
     *
     * @link https://msdn.microsoft.com/en-us/library/ms679021(v=vs.85).aspx
     *
     * @return string|null
     */
    public function getObjectGuid()
    {
        return $this->getFirstAttribute($this->guidKey);
    }

    /**
     * Returns the model's GUID.
     *
     * @return string|null
     */
    public function getConvertedGuid()
    {
        try {
            return (string) new Guid($this->getObjectGuid());
        } catch (InvalidArgumentException $e) {
            return;
        }
    }

    /**
     * Determine if the current model is located inside the given OU.
     *
     * If a model instance is given, the strict parameter is ignored.
     *
     * @param Model|string $ou     The organizational unit to check.
     * @param bool         $strict Whether the check is case-sensitive.
     *
     * @return bool
     */
    public function inOu($ou, $strict = false)
    {
        if ($ou instanceof static) {
            // If we've been given an OU model, we can
            // just check if the OU's DN is inside
            // the current models DN.
            return (bool) strpos($this->getDn(), $ou->getDn());
        }

        $suffix = $strict ? '' : 'i';

        $dn = $this->getNewDnBuilder($this->getDn());

        return (bool) preg_grep("/{$ou}/{$suffix}", $dn->getComponents('ou'));
    }

    /**
     * Saves the changes to LDAP and returns the results.
     *
     * @param array $attributes The attributes to update or create for the current entry.
     *
     * @return bool
     */
    public function save(array $attributes = [])
    {
        $this->fireModelEvent(new Events\Saving($this));

        $saved = $this->exists ? $this->update($attributes) : $this->create($attributes);

        if ($saved) {
            $this->fireModelEvent(new Events\Saved($this));
        }

        return $saved;
    }

    /**
     * Creates the entry in LDAP.
     *
     * @param array $attributes The attributes for the new entry.
     *
     * @throws UnexpectedValueException
     *
     * @return bool
     */
    public function create(array $attributes = [])
    {
        $this->fill($attributes);

        $query = static::query();

        if (empty($this->getDn())) {
            // If the model doesn't currently have a distinguished
            // name set, we'll create one automatically using
            // the current query builders base DN.
            $dn = $this->getCreatableDn();

            // If the dn we receive is the same as our queries base DN, we need
            // to throw an exception. The LDAP object must have a valid RDN.
            if ($dn->get() == $query->getDn()) {
                throw new UnexpectedValueException("An LDAP object must have a valid RDN to be created. '$dn' given.");
            }

            $this->setDn($dn);
        }

        $this->fireModelEvent(new Events\Creating($this));

        if ($query->create()) {
            // If the entry was created we'll re-sync
            // the models attributes from the server.
            $this->syncRaw();

            $this->fireModelEvent(new Events\Created($this));

            return true;
        }

        return false;
    }

    /**
     * Creates an attribute on the current model.
     *
     * @param string $attribute The attribute to create
     * @param mixed  $value     The value of the new attribute
     * @param bool   $sync      Whether to re-sync all attributes
     *
     * @throws ModelDoesNotExistException
     *
     * @return bool
     */
    public function createAttribute($attribute, $value, $sync = true)
    {
        if (! $this->exists) {
            throw (new ModelDoesNotExistException())->setModel($this);
        }

        if (static::query()->createAttribute([$attribute => $value])) {
            if ($sync) {
                $this->syncRaw();
            }

            return true;
        }

        return false;
    }

    /**
     * Updates the model.
     *
     * @param array $attributes The attributes to update for the current entry.
     *
     * @throws ModelDoesNotExistException
     *
     * @return bool
     */
    public function update(array $attributes = [])
    {
        if (!$this->exists) {
            throw (new ModelDoesNotExistException())->setModel($this);
        }

        $this->fill($attributes);

        $this->fireModelEvent(new Events\Updating($this));

        if (static::query()->update()) {
            // Re-sync attributes.
            $this->syncRaw();

            $this->fireModelEvent(new Events\Updated($this));

            // Re-set the models modifications.
            $this->modifications = [];
        }

        return false;
    }

    /**
     * Updates the specified attribute with the specified value.
     *
     * @param string $attribute The attribute to modify
     * @param mixed  $value     The new value for the attribute
     * @param bool   $sync      Whether to re-sync all attributes
     *
     * @throws ModelDoesNotExistException
     *
     * @return bool
     */
    public function updateAttribute($attribute, $value, $sync = true)
    {
        if (!$this->exists) {
            throw (new ModelDoesNotExistException())->setModel($this);
        }

        if (static::query()->updateAttribute([$attribute => $value])) {
            if ($sync) {
                $this->syncRaw();
            }

            return true;
        }

        return false;
    }

    /**
     * Deletes the current entry.
     *
     * Throws a ModelNotFoundException if the current model does
     * not exist or does not contain a distinguished name.
     *
     * @param bool $recursive Whether to recursively delete leaf nodes (models that are children).
     *
     * @throws ModelDoesNotExistException
     *
     * @return bool
     */
    public function delete($recursive = false)
    {
        if (! $this->exists) {
            throw (new ModelDoesNotExistException())->setModel($this);
        }

        $this->fireModelEvent(new Events\Deleting($this));

        if ($recursive) {
            // If recursive is requested, we'll retrieve all direct leaf nodes
            // by executing a 'listing' and delete each resulting model.
            $this->query()->listing()->in($this->getDn())->get()->each(function (Model $model) use ($recursive) {
                $model->delete($recursive);
            });
        }

        if (static::query()->delete()) {
            // If the deletion was successful, we'll mark the model
            // as non-existing and fire the deleted event.
            $this->exists = false;

            $this->fireModelEvent(new Events\Deleted($this));

            return true;
        }

        return false;
    }

    /**
     * Deletes an attribute on the current entry.
     *
     * @param string|array $attributes The attribute(s) to delete
     * @param bool         $sync       Whether to re-sync all attributes
     *
     * Delete specific values in attributes:
     *
     *     ["memberuid" => "username"]
     *
     * Delete an entire attribute:
     *
     *     ["memberuid" => []]
     *
     * @throws ModelDoesNotExistException
     *
     * @return bool
     */
    public function deleteAttribute($attributes, $sync = true)
    {
        if (!$this->exists) {
            throw (new ModelDoesNotExistException())->setModel($this);
        }

        // If we've been given a string, we'll assume we're removing a
        // single attribute. Otherwise, we'll assume it's
        // an array of attributes to remove.
        $attributes = is_string($attributes) ? [$attributes => []] : $attributes;

        if (static::query()->deleteAttribute($attributes)) {
            if ($sync) {
                $this->syncRaw();
            }

            return true;
        }

        return false;
    }

    /**
     * Moves the current model into the given new parent.
     *
     * For example: $user->move($ou);
     *
     * @param Model|string $newParentDn  The new parent of the current model.
     * @param bool         $deleteOldRdn Whether to delete the old models relative distinguished name once renamed / moved.
     *
     * @throws ModelDoesNotExistException
     *
     * @return bool
     */
    public function move($newParentDn, $deleteOldRdn = true)
    {
        // First we'll explode the current models distinguished name and keep their attributes prefixes.
        $parts = Utilities::explodeDn($this->getDn(), $removeAttrPrefixes = false);

        // If the current model has an empty RDN, we can't move it.
        if ((int) Arr::first($parts) === 0) {
            throw new UnexpectedValueException('Current model does not contain an RDN to move.');
        }

        // Looks like we have a DN. We'll retrieve the leftmost RDN (the identifier).
        $rdn = Arr::get($parts, 0);

        return $this->rename($rdn, $newParentDn, $deleteOldRdn);
    }

    /**
     * Renames the current model to a new RDN and new parent.
     *
     * @param string            $rdn          The models new relative distinguished name. Example: "cn=JohnDoe"
     * @param Model|string|null $newParentDn  The models new parent distinguished name (if moving). Leave this null if you are only renaming. Example: "ou=MovedUsers,dc=acme,dc=org"
     * @param bool|true         $deleteOldRdn Whether to delete the old models relative distinguished name once renamed / moved.
     *
     * @throws ModelDoesNotExistException
     *
     * @return bool
     */
    public function rename($rdn, $newParentDn = null, $deleteOldRdn = true)
    {
        if (!$this->exists) {
            throw (new ModelDoesNotExistException())->setModel($this);
        }

        if ($newParentDn instanceof self) {
            $newParentDn = $newParentDn->getDn();
        }

        if (static::query()->rename($rdn, $newParentDn, $deleteOldRdn)) {
            // If the model was successfully moved, we'll set its
            // new DN so we can sync it's attributes properly.
            $this->setDn("{$rdn},{$newParentDn}");

            $this->syncRaw();

            return true;
        }

        return false;
    }

    /**
     * Builds a new distinguished name that is creatable.
     *
     * @return DistinguishedName
     */
    protected function getCreatableDn()
    {
        return $this->getNewDnBuilder($this->getDn())->addCn($this->getFirstAttribute('cn'));
    }

    /**
     * Determines if the given modification is valid.
     *
     * @param mixed $mod
     *
     * @return bool
     */
    protected function isValidModification($mod)
    {
        return is_array($mod) &&
            array_key_exists(BatchModification::KEY_MODTYPE, $mod) &&
            array_key_exists(BatchModification::KEY_ATTRIB, $mod);
    }

    /**
     * Builds the models modifications from its dirty attributes.
     *
     * @return array
     */
    protected function buildModificationsFromDirty()
    {
        foreach ($this->getDirty() as $attribute => $values) {
            // Make sure values is always an array.
            $values = (is_array($values) ? $values : [$values]);

            // Create a new modification.
            $modification = $this->newBatchModification($attribute, null, $values);

            if (array_key_exists($attribute, $this->original)) {
                // If the attribute we're modifying has an original value, we'll give the
                // BatchModification object its values to automatically determine
                // which type of LDAP operation we need to perform.
                $modification->setOriginal($this->original[$attribute]);
            }

            // Build the modification from its
            // possible original values.
            $modification->build();

            if ($modification->isValid()) {
                // Finally, we'll add the modification to the model.
                $this->addModification($modification);
            }
        }

        return $this->modifications;
    }

    /**
     * Validates that the current LDAP connection is secure.
     *
     * @throws ConnectionException
     *
     * @return void
     */
    protected function validateSecureConnection()
    {
        if (!$this->getConnection()->getLdapConnection()->canChangePasswords()) {
            throw new ConnectionException(
                'You must be connected to your LDAP server with TLS or SSL to perform this operation.'
            );
        }
    }
}
