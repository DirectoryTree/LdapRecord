<?php

namespace LdapRecord\Query\Operations;

class Delete extends Operation
{
    /**
     * Delete the model.
     *
     * @return bool
     */
    public function execute()
    {
        return $this->ldap->delete($this->model->getDn());
    }
}
