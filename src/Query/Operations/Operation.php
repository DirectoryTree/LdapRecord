<?php

namespace LdapRecord\Query\Operations;

use LdapRecord\Models\Model;
use LdapRecord\Connections\LdapInterface;

abstract class Operation
{
    /**
     * @var LdapInterface
     */
    protected $ldap;

    /**
     * @var Model
     */
    protected $model;

    /**
     * Constructor.
     *
     * @param LdapInterface $ldap
     * @param Model         $model
     */
    public function __construct(LdapInterface $ldap, Model $model)
    {
        $this->ldap = $ldap;
        $this->model = $model;
    }

    /**
     * Execute the LDAP operation.
     *
     * @return mixed
     */
    abstract public function execute();
}
