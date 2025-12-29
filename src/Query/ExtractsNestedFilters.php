<?php

namespace LdapRecord\Query;

use LdapRecord\Query\Filter\Filter;
use LdapRecord\Query\Filter\GroupFilter;

trait ExtractsNestedFilters
{
    /**
     * Extract filters from a nested group filter for re-wrapping, preserving nested groups.
     *
     * @return array<Filter>
     */
    protected function extractNestedFilters(Filter $filter): array
    {
        if (! $filter instanceof GroupFilter) {
            return [$filter];
        }

        $children = $filter->getFilters();

        // If any child is a group, preserve the structure.
        foreach ($children as $child) {
            if ($child instanceof GroupFilter) {
                return $children;
            }
        }

        // All children are non-groups, it's safe to unwrap.
        return $children;
    }
}
