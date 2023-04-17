<?php

namespace LdapRecord\Query\Pagination;

use LdapRecord\LdapInterface;

class Paginator extends AbstractPaginator
{
    /**
     * {@inheritdoc}
     */
    protected function fetchCookie(): ?string
    {
        return $this->query->controls[LDAP_CONTROL_PAGEDRESULTS]['value']['cookie'] ?? null;
    }

    /**
     * {@inheritdoc}
     */
    protected function prepareServerControls(): void
    {
        $this->query->addControl(LDAP_CONTROL_PAGEDRESULTS, $this->isCritical, [
            'size' => $this->perPage, 'cookie' => '',
        ]);
    }

    /**
     * {@inheritdoc}
     */
    protected function applyServerControls(LdapInterface $ldap): void
    {
        $ldap->setOption(LDAP_OPT_SERVER_CONTROLS, $this->query->controls);
    }

    /**
     * {@inheritdoc}
     */
    protected function updateServerControls(LdapInterface $ldap, mixed $resource): void
    {
        $errorCode = 0;
        $dn = $errorMessage = $refs = null;
        $controls = $this->query->controls;

        $ldap->parseResult(
            $resource,
            $errorCode,
            $dn,
            $errorMessage,
            $refs,
            $controls
        );

        $cookie = $controls[LDAP_CONTROL_PAGEDRESULTS]['value']['cookie'] ?? '';

        $this->query->controls[LDAP_CONTROL_PAGEDRESULTS]['value'] = [
            'size' => $this->perPage,
            'cookie' => $cookie,
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function resetServerControls(LdapInterface $ldap): void
    {
        unset($this->query->controls[LDAP_CONTROL_PAGEDRESULTS]);
    }
}
