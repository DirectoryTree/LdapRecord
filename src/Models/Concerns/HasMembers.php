<?php

namespace LdapRecord\Models\Concerns;

use InvalidArgumentException;
use LdapRecord\Models\Model;
use LdapRecord\Models\BatchModification;
use LdapRecord\Models\Relations\Relation;

trait HasMembers
{
    /**
     * The members relationship.
     *
     * @return Relation
     */
    abstract public function members() : Relation;

    /**
     * Get a new member batch modification.
     *
     * @param int   $type
     * @param array $members
     *
     * @return BatchModification
     */
    abstract public function newMemberModification($type, array $members = []) : BatchModification;

    /**
     * Add member models to the current.
     *
     * @param array $members
     *
     * @return bool
     */
    public function addMembers(array $members)
    {
        $members = array_map(function ($member) {
            return $member instanceof Model
                ? $member->getDn()
                : $member;
        }, $members);

        return $this->addModification(
            $this->newMemberModification(LDAP_MODIFY_BATCH_ADD, $members)
        )->save();
    }

    /**
     * Add a model member to the current.
     *
     * @param \LdapRecord\Models\Model|string $member
     *
     * @throws InvalidArgumentException When the given entry is empty or contains no distinguished name.
     *
     * @return bool
     */
    public function addMember($member)
    {
        $member = $member instanceof Model ? $member->getDn() : $member;

        if (is_null($member)) {
            throw new InvalidArgumentException(
                'Cannot add member to group. The members distinguished name cannot be null.'
            );
        }

        return $this->addModification(
            $this->newMemberModification(LDAP_MODIFY_BATCH_ADD, [$member])
        )->save();
    }

    /**
     * Remove a member model apart of the current.
     *
     * @param \LdapRecord\Models\Model|string $member
     *
     * @throws InvalidArgumentException
     *
     * @return bool
     */
    public function removeMember($member)
    {
        $member = $member instanceof Model ? $member->getDn() : $member;

        if (is_null($member)) {
            throw new InvalidArgumentException(
                'Cannot remove member to group. The members distinguished name cannot be null.'
            );
        }

        return $this->addModification(
            $this->newMemberModification(LDAP_MODIFY_BATCH_REMOVE, [$member])
        )->save();
    }

    /**
     * Remove all member models apart of the current.
     *
     * @return bool
     */
    public function removeMembers()
    {
        return $this->addModification(
            $this->newMemberModification(LDAP_MODIFY_BATCH_REMOVE_ALL)
        )->save();
    }
}
