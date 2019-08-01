<?php

namespace LdapRecord\Query\Operations;

use LdapRecord\Models\Model;
use LdapRecord\Connections\LdapInterface;

class Rename extends Operation
{
    /**
     * The models relative distinguished name.
     *
     * @var string
     */
    protected $rdn;

    /**
     * The models new parent distinguished name.
     *
     * @var string
     */
    protected $newParentDn;

    /**
     * Whether to delete the models old RDN.
     *
     * @var bool
     */
    protected $deleteOldRdn;

    /**
     * Constructor.
     *
     * @param LdapInterface $ldap
     * @param Model         $model
     * @param string        $rdn
     * @param string        $newParentDn
     * @param bool          $deleteOldRdn
     */
    public function __construct(LdapInterface $ldap, Model $model, $rdn, $newParentDn, $deleteOldRdn = true)
    {
        parent::__construct($ldap, $model);

        $this->rdn = $rdn;
        $this->newParentDn = $newParentDn;
        $this->deleteOldRdn = $deleteOldRdn;
    }

    /**
     * Renames the model.
     *
     * @return bool
     */
    public function execute()
    {
        return $this->ldap->rename($this->model->getDn(), $this->rdn, $this->newParentDn, $this->deleteOldRdn);
    }
}