<?php

namespace LdapRecord\Query\Operations;

class Update extends Operation
{
    /**
     * Update the model.
     *
     * @return bool
     */
    public function execute()
    {
        return $this->ldap->modifyBatch($this->model->getDn(), $this->model->getModifications());
    }
}
