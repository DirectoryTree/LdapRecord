<?php

namespace LdapRecord\Query\Pagination;

use LdapRecord\LdapInterface;

class DeprecatedPaginator extends Paginator
{
    /**
     * Execute the pagination request (for PHP <= 7.4).
     *
     * @param LdapInterface $ldap
     *
     * @return array
     */
    public function execute(LdapInterface $ldap)
    {
        $pages = [];

        $cookie = '';

        do {
            $ldap->controlPagedResult($this->perPage, $this->isCritical, $cookie);

            $resource = $this->query->run($this->filter);

            if ($resource) {
                // If we have been given a valid resource, we will retrieve the next
                // pagination cookie to send for our next pagination request.
                $ldap->controlPagedResultResponse($resource, $cookie);

                $pages[] = $this->query->parse($resource);
            }
        } while (!empty($cookie));

        // Reset paged result on the current connection. We won't pass in the current $perPage
        // parameter since we want to reset the page size to the default '1000'. Sending '0'
        // eliminates any further opportunity for running queries in the same request,
        // even though that is supposed to be the correct usage.
        $ldap->controlPagedResult();

        return $pages;
    }
}
