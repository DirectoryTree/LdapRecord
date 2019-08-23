<?php

namespace LdapRecord\Query\Model;

class ActiveDirectoryBuilder extends Builder
{
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
