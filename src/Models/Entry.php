<?php

namespace LdapRecord\Models;

class Entry extends Model
{
    /**
     * Begin querying the direct descendants of the model.
     *
     * @return \LdapRecord\Query\Model\Builder
     */
    public function descendants()
    {
        return $this->in($this->getDn())->listing();
    }

    /**
     * Begin querying the direct ancestors of the model.
     *
     * @return \LdapRecord\Query\Model\Builder
     */
    public function ancestors()
    {
        return $this->in($this->getParentDn($this->getDn()))->listing();
    }
}
