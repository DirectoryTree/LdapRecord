<?php

namespace LdapRecord\Query\Pagination;

use LdapRecord\LdapInterface;
use LdapRecord\Query\Builder;

class Paginator
{
    /**
     * The query builder instance.
     *
     * @var Builder
     */
    protected $query;

    /**
     * The filter to execute.
     *
     * @var string
     */
    protected $filter;

    /**
     * The amount of objects to fetch per page.
     *
     * @var int
     */
    protected $perPage;

    /**
     * Whether the operation is critical.
     *
     * @var bool
     */
    protected $isCritical;

    /**
     * Constructor.
     *
     * @param Builder $query
     */
    public function __construct(Builder $query, $filter, $perPage, $isCritical)
    {
        $this->query = $query;
        $this->filter = $filter;
        $this->perPage = $perPage;
        $this->isCritical = $isCritical;
    }

    /**
     * Execute the pagination request.
     *
     * @param LdapInterface $ldap
     *
     * @return array
     */
    public function execute(LdapInterface $ldap)
    {
        $pages = [];

        // Add our paged results control.
        $this->query->addControl(LDAP_CONTROL_PAGEDRESULTS, $this->isCritical, [
            'size' => $this->perPage, 'cookie' => '',
        ]);

        do {
            // Update the server controls.
            $ldap->setOption(LDAP_OPT_SERVER_CONTROLS, $this->query->controls);

            $resource = $this->query->run($this->filter);

            if ($resource) {
                $errorCode = $dn = $errorMessage = $refs = null;

                // Update the server controls with the servers response.
                $ldap->parseResult(
                    $resource,
                    $errorCode,
                    $dn,
                    $errorMessage,
                    $refs,
                    $this->query->controls
                );

                $pages[] = $this->query->parse($resource);

                // Here we will update the query's server controls array with the servers
                // response by passing the array as a reference. The cookie string will
                // be empty once the pagination request has successfully completed.
                $this->query->controls[LDAP_CONTROL_PAGEDRESULTS]['value']['size'] = $this->perPage;
            }
        } while (!empty($this->query->controls[LDAP_CONTROL_PAGEDRESULTS]['value']['cookie']));

        return $pages;
    }
}
