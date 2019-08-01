<?php

namespace LdapRecord\Query\Operations;

use LdapRecord\Models\Model;
use LdapRecord\Connections\LdapInterface;

class DeleteAttribute extends Operation
{
    /**
     * The attributes being added to the model.
     *
     * @var array
     */
    protected $attributes;

    /**
     * Constructor.
     *
     * @param LdapInterface $ldap
     * @param Model         $model
     * @param array         $attributes
     */
    public function __construct(LdapInterface $ldap, Model $model, array $attributes)
    {
        parent::__construct($ldap, $model);

        $this->attributes = $attributes;
    }

    /**
     * Delete the attributes on the model.
     *
     * @return mixed
     */
    public function execute()
    {
        return $this->ldap->modDelete($this->model->getDn(), $this->attributes);
    }
}
