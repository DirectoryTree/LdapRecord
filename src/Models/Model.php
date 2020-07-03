<?php

namespace LdapRecord\Models;

use ArrayAccess;
use JsonSerializable;
use LdapRecord\Container;
use LdapRecord\Connection;
use InvalidArgumentException;
use LdapRecord\EscapesValues;
use UnexpectedValueException;
use LdapRecord\Query\Collection;
use LdapRecord\Query\Model\Builder;
use LdapRecord\Models\Events\Renamed;
use LdapRecord\Models\Attributes\Guid;
use LdapRecord\Models\Events\Renaming;
use LdapRecord\Models\Attributes\DistinguishedName;

/** @mixin Builder */
abstract class Model implements ArrayAccess, JsonSerializable
{
    use EscapesValues;
    use Concerns\HasEvents;
    use Concerns\HasScopes;
    use Concerns\HasAttributes;
    use Concerns\HasGlobalScopes;
    use Concerns\HidesAttributes;
    use Concerns\HasRelationships;

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
     * The base DN of where the model should be created in.
     *
     * @var string|null
     */
    protected $in;

    /**
     * The object classes of the LDAP model.
     *
     * @var array
     */
    public static $objectClasses = [];

    /**
     * The connection container instance.
     *
     * @var Container
     */
    protected static $container;

    /**
     * The LDAP connection name for the model.
     *
     * @var string|null
     */
    protected $connection;

    /**
     * The attribute key that contains the models object GUID.
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
     * The array of global scopes on the model.
     *
     * @var array
     */
    protected static $globalScopes = [];

    /**
     * The array of booted models.
     *
     * @var array
     */
    protected static $booted = [];

    /**
     * Constructor.
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->bootIfNotBooted();

        $this->fill($attributes);
    }

    /**
     * Check if the model needs to be booted and if so, do it.
     *
     * @return void
     */
    protected function bootIfNotBooted()
    {
        if (!isset(static::$booted[static::class])) {
            static::$booted[static::class] = true;

            static::boot();
        }
    }

    /**
     * The "booting" method of the model.
     *
     * @return void
     */
    protected static function boot()
    {
        //
    }

    /**
     * Clear the list of booted models so they will be re-booted.
     *
     * @return void
     */
    public static function clearBootedModels()
    {
        static::$booted = [];

        static::$globalScopes = [];
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
        return (new static())->$method(...$parameters);
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
        $this->dn = (string) $dn;

        return $this;
    }

    /**
     * Get the LDAP connection for the model.
     *
     * @return Connection
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
        $instance = new static();

        $instance->setConnection($connection);

        return $instance->newQuery();
    }

    /**
     * Get all the models from the directory.
     *
     * @param array|mixed $attributes
     *
     * @return Collection|static[]
     */
    public static function all($attributes = ['*'])
    {
        return static::query()->select($attributes)->paginate();
    }

    /**
     * Begin querying the model.
     *
     * @return Builder
     */
    public static function query()
    {
        return (new static())->newQuery();
    }

    /**
     * Get a new query for builder filtered by the current models object classes.
     *
     * @return Builder
     */
    public function newQuery()
    {
        return $this->registerModelScopes(
            $this->newQueryWithoutScopes()
        );
    }

    /**
     * Get a new query builder that doesn't have any global scopes.
     *
     * @return \LdapRecord\Query\Model\Builder
     */
    public function newQueryWithoutScopes()
    {
        return static::resolveConnection($this->connection)->query()->model($this);
    }

    /**
     * Create a new query builder.
     *
     * @param Connection $connection
     *
     * @return Builder
     */
    public function newQueryBuilder(Connection $connection)
    {
        return (new Builder($connection))->setCache($connection->getCache());
    }

    /**
     * Create a new model instance.
     *
     * @param array $attributes
     *
     * @return static
     */
    public function newInstance(array $attributes = [])
    {
        return (new static($attributes))->setConnection($this->getConnectionName());
    }

    /**
     * Resolve a connection instance.
     *
     * @param string|null $connection
     *
     * @return \LdapRecord\Connection
     */
    public static function resolveConnection($connection = null)
    {
        return static::getConnectionContainer()->get($connection);
    }

    /**
     * Get the connection container.
     *
     * @return Container
     */
    public static function getConnectionContainer()
    {
        return static::$container ?? static::getDefaultConnectionContainer();
    }

    /**
     * Get the default singleton container instance.
     *
     * @return Container
     */
    public static function getDefaultConnectionContainer()
    {
        return Container::getInstance();
    }

    /**
     * Set the connection container.
     *
     * @param Container $container
     *
     * @return void
     */
    public static function setConnectionContainer(Container $container)
    {
        static::$container = $container;
    }

    /**
     * Unset the connection container.
     *
     * @return void
     */
    public static function unsetConnectionContainer()
    {
        static::$container = null;
    }

    /**
     * Register the query scopes for this builder instance.
     *
     * @param Builder $builder
     *
     * @return Builder
     */
    public function registerModelScopes($builder)
    {
        $this->applyObjectClassScopes($builder);

        $this->registerGlobalScopes($builder);

        return $builder;
    }

    /**
     * Register the global model scopes.
     *
     * @param Builder $builder
     *
     * @return Builder
     */
    public function registerGlobalScopes($builder)
    {
        foreach ($this->getGlobalScopes() as $identifier => $scope) {
            $builder->withGlobalScope($identifier, $scope);
        }

        return $builder;
    }

    /**
     * Apply the model object class scopes to the given builder instance.
     *
     * @param Builder $query
     *
     * @return void
     */
    public function applyObjectClassScopes(Builder $query)
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
        return !is_null($this->getAttribute($offset));
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
        return $this->attributesToArray();
    }

    /**
     * Converts extra attributes for JSON serialization.
     *
     * @param array $attributes
     *
     * @return array
     */
    protected function convertAttributesForJson(array $attributes = [])
    {
        // If the model has a GUID set, we need to convert
        // it due to it being in binary. Otherwise we'll
        // receive a JSON serialization exception.
        if ($this->hasAttribute($this->guidKey)) {
            return array_replace($attributes, [
                $this->guidKey => [$this->getConvertedGuid()],
            ]);
        }

        return $attributes;
    }

    /**
     * Reload a fresh model instance from the directory.
     *
     * @return static|false
     */
    public function fresh()
    {
        if (!$this->exists) {
            return false;
        }

        return $this->newQuery()->find($this->dn);
    }

    /**
     * Determine if two models have the same distinguished name and belong to the same connection.
     *
     * @param static $model
     *
     * @return bool
     */
    public function is(self $model)
    {
        return $this->dn == $model->getDn() && $this->connection == $model->getConnectionName();
    }

    /**
     * Hydrate a new collection of models from LDAP search results.
     *
     * @param array $records
     *
     * @return Collection
     */
    public function hydrate($records)
    {
        return $this->newCollection($records)->transform(function ($attributes) {
            return static::newInstance()->setRawAttributes($attributes);
        });
    }

    /**
     * Converts the current model into the given model.
     *
     * @param Model $into
     *
     * @return Model
     */
    public function convert(self $into)
    {
        $into->setDn($this->getDn());
        $into->setConnection($this->getConnectionName());

        $this->exists
            ? $into->setRawAttributes($this->getAttributes())
            : $into->fill($this->getAttributes());

        return $into;
    }

    /**
     * Synchronizes the current models attributes with the directory values.
     *
     * @return bool
     */
    public function synchronize()
    {
        if ($model = $this->fresh()) {
            $this->setRawAttributes($model->getAttributes());

            return true;
        }

        return false;
    }

    /**
     * Get the models batch modifications to be processed.
     *
     * @return array
     */
    public function getModifications()
    {
        $builtModifications = [];

        foreach ($this->buildModificationsFromDirty() as $modification) {
            $builtModifications[] = $modification->get();
        }

        return array_merge($this->modifications, $builtModifications);
    }

    /**
     * Set the models batch modifications.
     *
     * @param array $modifications
     *
     * @return $this
     */
    public function setModifications(array $modifications = [])
    {
        $this->modifications = [];

        foreach ($modifications as $modification) {
            $this->addModification($modification);
        }

        return $this;
    }

    /**
     * Adds a batch modification to the model.
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
     * Get the models guid attribute key name.
     *
     * @return string
     */
    public function getGuidKey()
    {
        return $this->guidKey;
    }

    /**
     * Get the models ANR attributes for querying when incompatible with ANR.
     *
     * @return array
     */
    public function getAnrAttributes()
    {
        return ['cn', 'sn', 'uid', 'name', 'mail', 'givenname', 'displayname'];
    }

    /**
     * Get the name of the model, or the given DN.
     *
     * @param string|null $dn
     *
     * @return string|null
     */
    public function getName($dn = null)
    {
        return (new DistinguishedName($dn ?? $this->dn))->name();
    }

    /**
     * Get the RDN of the model, of the given DN.
     *
     * @param string|null
     *
     * @return string|null
     */
    public function getRdn($dn = null)
    {
        return (new DistinguishedName($dn ?? $this->dn))->relative();
    }

    /**
     * Get the parent distinguished name of the model, or the given DN.
     *
     * @param string|null
     *
     * @return string|null
     */
    public function getParentDn($dn = null)
    {
        return (new DistinguishedName($dn ?? $this->dn))->parent();
    }

    /**
     * Get the models binary object GUID.
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
     * Get the models string GUID.
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
     * Determine if the current model is a direct descendant of the given.
     *
     * @param static|string $parent
     *
     * @return bool
     */
    public function isChildOf($parent)
    {
        return (new DistinguishedName($this->getDn()))->isChildOf(
            new DistinguishedName((string) $parent)
        );
    }

    /**
     * Determine if the current model is a direct ascendant of the given.
     *
     * @param static|string $child
     *
     * @return bool
     */
    public function isParentOf($child)
    {
        return (new DistinguishedName($this->getDn()))->isParentOf(
            new DistinguishedName((string) $child)
        );
    }

    /**
     * Determine if the current model is a descendant of the given.
     *
     * @param static|string $model
     *
     * @return bool
     */
    public function isDescendantOf($model)
    {
        return $this->dnIsInside($this->getDn(), $model);
    }

    /**
     * Determine if the current model is a ancestor of the given.
     *
     * @param static|string $model
     *
     * @return bool
     */
    public function isAncestorOf($model)
    {
        return $this->dnIsInside($model, $this->getDn());
    }

    /**
     * Determines if the DN is inside of the parent DN.
     *
     * @param static|string $dn
     * @param static|string $parentDn
     *
     * @return bool
     */
    protected function dnIsInside($dn, $parentDn)
    {
        return (new DistinguishedName((string) $dn))->isDescendantOf(
            new DistinguishedName($parentDn)
        );
    }

    /**
     * Set the base DN of where the model should be created in.
     *
     * @param static|string $dn
     *
     * @return $this
     */
    public function inside($dn)
    {
        $this->in = $dn instanceof self ? $dn->getDn() : $dn;

        return $this;
    }

    /**
     * Save the model to the directory.
     *
     * @param array $attributes The attributes to update or create for the current entry.
     *
     * @return bool
     */
    public function save(array $attributes = [])
    {
        $this->fill($attributes);

        $this->fireModelEvent(new Events\Saving($this));

        $saved = $this->exists ? $this->performUpdate() : $this->performInsert();

        if ($saved) {
            $this->fireModelEvent(new Events\Saved($this));
        }

        return $saved;
    }

    /**
     * Inserts the model into the directory.
     *
     * @throws \Exception
     *
     * @return bool
     */
    protected function performInsert()
    {
        // Here we will populate the models object classes if it
        // does not already have any. An LDAP object cannot
        // be successfully created without them set.
        if (!$this->hasAttribute('objectclass')) {
            $this->setAttribute('objectclass', static::$objectClasses);
        }

        $query = $this->newQuery();

        // If the model does not currently have a distinguished
        // name, we will attempt to create one automatically
        // using the current query builders DN as a base.
        if (empty($this->getDn())) {
            $this->setDn($this->getCreatableDn());
        }

        $this->fireModelEvent(new Events\Creating($this));

        // Here we perform the insert of the object in the directory.
        // We will also filter out any empty attribute values here,
        // otherwise the LDAP server will return an error message.
        if ($query->insert($this->getDn(), array_filter($this->getAttributes()))) {
            $this->fireModelEvent(new Events\Created($this));

            $this->exists = true;

            return $this->synchronize();
        }

        return false;
    }

    /**
     * Updates the model in the directory.
     *
     * @throws \Exception
     *
     * @return bool
     */
    protected function performUpdate()
    {
        $modifications = $this->getModifications();

        if (count($modifications) > 0) {
            $this->fireModelEvent(new Events\Updating($this));

            if ($this->newQuery()->update($this->dn, $modifications)) {
                $this->fireModelEvent(new Events\Updated($this));

                // Re-set the models modifications.
                $this->modifications = [];

                // Re-sync the models attributes.
                return $this->synchronize();
            }

            return false;
        }

        return true;
    }

    /**
     * Create the model in the directory.
     *
     * @param array $attributes The attributes for the new entry.
     *
     * @throws \Exception
     *
     * @return Model
     */
    public static function create(array $attributes = [])
    {
        $instance = new static($attributes);

        $instance->save();

        return $instance;
    }

    /**
     * Create an attribute on the model.
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
        $this->validateExistence();

        if ($this->newQuery()->insertAttributes($this->dn, [$attribute => (array) $value])) {
            return $sync ? $this->synchronize() : true;
        }

        return false;
    }

    /**
     * Update the model.
     *
     * @param array $attributes The attributes to update for the current entry.
     *
     * @throws ModelDoesNotExistException
     *
     * @return bool
     */
    public function update(array $attributes = [])
    {
        $this->validateExistence();

        return $this->save($attributes);
    }

    /**
     * Update the model attribute with the specified value.
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
        $this->validateExistence();

        if ($this->newQuery()->updateAttributes($this->dn, [$attribute => (array) $value])) {
            return $sync ? $this->synchronize() : true;
        }

        return false;
    }

    /**
     * Destroy the models for the given distinguished names.
     *
     * @param Collection|array|string $dns
     * @param bool                    $recursive
     *
     * @return int
     */
    public static function destroy($dns, $recursive = false)
    {
        $count = 0;

        $dns = $dns instanceof Collection ? $dns->all() : (array) $dns;

        $instance = new static();

        foreach ($dns as $dn) {
            $model = $instance->find($dn);

            if ($model && $model->delete($recursive)) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Delete the model from the directory.
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
        $this->validateExistence();

        $this->fireModelEvent(new Events\Deleting($this));

        if ($recursive) {
            $this->deleteLeafNodes();
        }

        if ($this->newQuery()->delete($this->dn)) {
            // If the deletion is successful, we will mark the model
            // as non-existing, and then fire the deleted event so
            // developers can hook in and run further operations.
            $this->exists = false;

            $this->fireModelEvent(new Events\Deleted($this));

            return true;
        }

        return false;
    }

    /**
     * Deletes leaf nodes that are attached to the model.
     *
     * @return Collection
     */
    protected function deleteLeafNodes()
    {
        return $this->newQuery()->listing()->in($this->dn)->get()->each(function (self $model) {
            $model->delete(true);
        });
    }

    /**
     * Delete an attribute on the model.
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
        $this->validateExistence();

        // If we have been given a string, we will assume we're
        // removing a single attribute. Otherwise, we will
        // assume it's an array of attributes to remove.
        $attributes = is_string($attributes) ? [$attributes => []] : $attributes;

        if ($this->newQuery()->deleteAttributes($this->dn, $attributes)) {
            return $sync ? $this->synchronize() : true;
        }

        return false;
    }

    /**
     * Move the model into the given new parent.
     *
     * For example: $user->move($ou);
     *
     * @param static|string $newParentDn  The new parent of the current model.
     * @param bool          $deleteOldRdn Whether to delete the old models relative distinguished name once renamed / moved.
     *
     * @throws ModelDoesNotExistException
     *
     * @return bool
     */
    public function move($newParentDn, $deleteOldRdn = true)
    {
        $this->validateExistence();

        if ($rdn = $this->getRdn()) {
            return $this->rename($rdn, $newParentDn, $deleteOldRdn);
        }

        throw new UnexpectedValueException('Current model does not contain an RDN to move.');
    }

    /**
     * Rename the model to a new RDN and new parent.
     *
     * @param string             $rdn          The models new relative distinguished name. Example: "cn=JohnDoe"
     * @param static|string|null $newParentDn  The models new parent distinguished name (if moving). Leave this null if you are only renaming. Example: "ou=MovedUsers,dc=acme,dc=org"
     * @param bool|true          $deleteOldRdn Whether to delete the old models relative distinguished name once renamed / moved.
     *
     * @throws ModelDoesNotExistException
     *
     * @return bool
     */
    public function rename($rdn, $newParentDn = null, $deleteOldRdn = true)
    {
        $this->validateExistence();

        if ($newParentDn instanceof self) {
            $newParentDn = $newParentDn->getDn();
        }

        if (is_null($newParentDn)) {
            $newParentDn = $this->getParentDn($this->dn);
        }

        $this->fireModelEvent(new Renaming($this, $rdn, $newParentDn));

        if ($this->newQuery()->rename($this->dn, $rdn, $newParentDn, $deleteOldRdn)) {
            // If the model was successfully renamed, we will set
            // its new DN so any further updates to the model
            // can be performed without any issues.
            $this->dn = implode(',', [$rdn, $newParentDn]);

            $this->fireModelEvent(new Renamed($this));

            return true;
        }

        return false;
    }

    /**
     * Get a distinguished name that is creatable for the model.
     *
     * @return string
     */
    public function getCreatableDn()
    {
        return implode(',', [$this->getCreatableRdn(), $this->in ?? $this->newQuery()->getDn()]);
    }

    /**
     * Get a creatable RDN for the model.
     *
     * @return string
     */
    public function getCreatableRdn()
    {
        $name = $this->escape($this->getFirstAttribute('cn'))->dn();

        return "cn=$name";
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
     * @return BatchModification[]
     */
    protected function buildModificationsFromDirty()
    {
        $modifications = [];

        foreach ($this->getDirty() as $attribute => $values) {
            $modification = $this->newBatchModification($attribute, null, (array) $values);

            if (array_key_exists($attribute, $this->original)) {
                // If the attribute we're modifying has an original value, we will
                // give the BatchModification object its values to automatically
                // determine which type of LDAP operation we need to perform.
                $modification->setOriginal($this->original[$attribute]);
            }

            if (!$modification->build()->isValid()) {
                continue;
            }

            $modifications[] = $modification;
        }

        return $modifications;
    }

    /**
     * Validates that the current model exists.
     *
     * @throws ModelDoesNotExistException
     *
     * @return void
     */
    protected function validateExistence()
    {
        if (!$this->exists || is_null($this->dn)) {
            throw ModelDoesNotExistException::forModel($this);
        }
    }
}
