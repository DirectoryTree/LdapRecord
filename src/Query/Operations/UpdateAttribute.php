<?php

namespace LdapRecord\Query\Operations;

use LdapRecord\Models\Model;
use LdapRecord\Connections\LdapInterface;

class UpdateAttribute extends Operation
{
    /**
     * The attributes being updated on the model.
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
     * Update the attributes on the model.
     *
     * @return mixed
     */
    public function execute()
    {
        return $this->ldap->modReplace($this->model->getDn(), $this->attributes);
    }
}
