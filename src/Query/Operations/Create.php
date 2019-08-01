<?php

namespace LdapRecord\Query\Operations;

class Create extends Operation
{
    /**
     * Create the model in the directory.
     *
     * @return bool
     */
    public function execute()
    {
        return $this->ldap->add($this->model->getDn(), $this->model->getAttributes());
    }
}
