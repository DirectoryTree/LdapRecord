<?php

namespace LdapRecord\Query\Model;

use LdapRecord\Models\Model;
use LdapRecord\Models\ModelNotFoundException;

class ActiveDirectoryBuilder extends Builder
{
    /**
     * Finds a record by its Object SID.
     *
     * @param string       $sid
     * @param array|string $columns
     *
     * @return Model|static|null
     */
    public function findBySid($sid, $columns = [])
    {
        try {
            return $this->findBySidOrFail($sid, $columns);
        } catch (ModelNotFoundException $e) {
            return;
        }
    }

    /**
     * Finds a record by its Object SID.
     *
     * Fails upon no records returned.
     *
     * @param string       $sid
     * @param array|string $columns
     *
     * @throws ModelNotFoundException
     *
     * @return Model|static
     */
    public function findBySidOrFail($sid, $columns = [])
    {
        return $this->findByOrFail('objectsid', $sid, $columns);
    }

    /**
     * Adds a enabled filter to the current query.
     *
     * @return $this
     */
    public function whereEnabled()
    {
        return $this->rawFilter('(!(UserAccountControl:1.2.840.113556.1.4.803:=2))');
    }

    /**
     * Adds a disabled filter to the current query.
     *
     * @return $this
     */
    public function whereDisabled()
    {
        return $this->rawFilter('(UserAccountControl:1.2.840.113556.1.4.803:=2)');
    }

    /**
     * Adds a 'member ' filter to the current query.
     *
     * @param string $dn
     *
     * @return $this
     */
    public function whereMember($dn)
    {
        return $this->whereEquals('member:1.2.840.113556.1.4.1941:', $dn);
    }

    /**
     * Adds an 'or member' filter to the current query.
     *
     * @param string $dn
     *
     * @return $this
     */
    public function orWhereMember($dn)
    {
        return $this->orWhereEquals('member:1.2.840.113556.1.4.1941:', $dn);
    }

    /**
     * Adds a 'member of' filter to the current query.
     *
     * @param string $dn
     *
     * @return $this
     */
    public function whereMemberOf($dn)
    {
        return $this->whereEquals('memberof:1.2.840.113556.1.4.1941:', $dn);
    }

    /**
     * Adds an 'or where member of' filter to the current query.
     *
     * @param string $dn
     *
     * @return $this
     */
    public function orWhereMemberOf($dn)
    {
        return $this->orWhereEquals('memberof:1.2.840.113556.1.4.1941:', $dn);
    }
}
