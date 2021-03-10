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

        $ldap->parseResult(
            $resource,
            $errorCode,
            $dn,
            $errorMessage,
            $refs,
            $this->query->controls
        );

        $this->resetPageSize();
    }

    protected function resetPageSize()
    {
        $this->query->controls[LDAP_CONTROL_PAGEDRESULTS]['value']['size'] = $this->perPage;
    }

    /**
     * {@inheritDoc}
     */
    protected function resetServerControls(LdapInterface $ldap)
    {
        $this->query->controls = [];
    }
}
