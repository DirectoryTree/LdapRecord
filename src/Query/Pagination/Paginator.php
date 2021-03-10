<?php

namespace LdapRecord\Query\Pagination;

use LdapRecord\LdapInterface;

class Paginator extends AbstractPaginator
{
    /**
     * {@inheritDoc}
     */
    protected function fetchCookie()
    {
        return $this->query->controls[LDAP_CONTROL_PAGEDRESULTS]['value']['cookie'];
    }

    /**
     * {@inheritDoc}
     */
    protected function prepareServerControls()
    {
        $this->query->addControl(LDAP_CONTROL_PAGEDRESULTS, $this->isCritical, [
            'size' => $this->perPage, 'cookie' => '',
        ]);
    }

    /**
     * {@inheritDoc}
     */
    protected function applyServerControls(LdapInterface $ldap)
    {
        $ldap->setOption(LDAP_OPT_SERVER_CONTROLS, $this->query->controls);
    }

    /**
     * {@inheritDoc}
     */
    protected function updateServerControls(LdapInterface $ldap, $resource)
    {
        $errorCode = $dn = $errorMessage = $refs = null;

        // Here we will update the query's server controls array with the servers
        // response by passing the array as a reference. The cookie string will
        // be empty once the pagination request has successfully completed.
        $ldap->parseResult(
            $resource,
            $errorCode,
            $dn,
            $errorMessage,
            $refs,
            $this->query->controls
        );
    }

    /**
     * {@inheritDoc}
     */
    protected function resetServerControls(LdapInterface $ldap)
    {
        $this->query->controls = [];
    }
}
