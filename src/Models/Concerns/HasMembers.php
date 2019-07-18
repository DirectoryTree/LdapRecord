<?php

namespace LdapRecord\Models\Concerns;

use LdapRecord\Models\User;
use LdapRecord\Models\Group;
use LdapRecord\Models\Contact;

trait HasMembers
{
    /**
     * The attribute key to retrieve members from.
     * 
     * @var array
     */
    protected $memberKey = 'member';

    /**
     * The members relationship.
     * 
     * @return \LdapRecord\Models\Relation
     */
    public function members()
    {
        return $this->hasMember([
            User::class, Contact::class, Group::class
        ], $this->memberKey);
    }
}
