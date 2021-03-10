<?php

namespace LdapRecord\Query\Pagination;

use LdapRecord\LdapInterface;

class DeprecatedPaginator extends AbstractPaginator
{
    /**
     * The pagination cookie.
     *
     * @var string
     */
    protected $cookie = '';

    /**
     * {@inheritDoc}
     */
    protected function fetchCookie()
    {
        return $this->cookie;
    }

    /**
     * {@inheritDoc}
     */
    protected function prepareServerControls()
    {
        $this->cookie = '';
    }

    /**
     * {@inheritDoc}
     */
    protected function applyServerControls(LdapInterface $ldap)
    {
        $ldap->controlPagedResult($this->perPage, $this->isCritical, $this->cookie);
    }

    /**
     * {@inheritDoc}
     */
    protected function updateServerControls(LdapInterface $ldap, $resource)
    {
        $ldap->controlPagedResultResponse($resource, $this->cookie);
    }

    /**
     * {@inheritDoc}
     */
    protected function resetServerControls(LdapInterface $ldap)
    {
        $ldap->controlPagedResult();
    }
}
