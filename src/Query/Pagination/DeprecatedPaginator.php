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
     * Execute the pagination request (for PHP <= 7.).
     *
     * @param LdapInterface $ldap
     *
     * @return array
     */
    public function execute(LdapInterface $ldap)
    {
        $pages = parent::execute($ldap);

        // Reset paged result on the current connection. We won't pass in the current $perPage
        // parameter since we want to reset the page size to the default '1000'. Sending '0'
        // eliminates any further opportunity for running queries in the same request,
        // even though that is supposed to be the correct usage.
        $ldap->controlPagedResult();

        return $pages;
    }

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
        $ldap->controlPagedResult($this->perPage, $this->isCritical, $this->cookie);
    }
}
