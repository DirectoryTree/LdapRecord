<?php

namespace LdapRecord\Query\Model;

use Closure;
use InvalidArgumentException;
use LdapRecord\LdapInterface;
use LdapRecord\Models\Attributes\AccountControl;
use LdapRecord\Models\Attributes\SecurityDescriptor;
use LdapRecord\Models\Model;
use LdapRecord\Models\ModelNotFoundException;

class ActiveDirectoryBuilder extends Builder
{
    /**
     * Include the selected security descriptor sections in query results.
     */
    public function withSecurityDescriptor(
        int $parts = SecurityDescriptor::OWNER_SECURITY_INFORMATION
            | SecurityDescriptor::GROUP_SECURITY_INFORMATION
            | SecurityDescriptor::DACL_SECURITY_INFORMATION,
    ): static {
        if ($parts < 1 || $parts > 15) {
            throw new InvalidArgumentException('Select at least one security descriptor section using flags 1 through 15.');
        }

        return $this->addSelect('ntsecuritydescriptor')->addControl(
            LdapInterface::OID_SERVER_SD_FLAGS,
            true,
            pack('C5', 0x30, 0x03, 0x02, 0x01, $parts)
        );
    }

    /**
     * Finds a record by its Object SID.
     */
    public function findBySid(string $sid, array|string $selects = ['*']): ?Model
    {
        try {
            return $this->findBySidOrFail($sid, $selects);
        } catch (ModelNotFoundException) {
            return null;
        }
    }

    /**
     * Finds a record by its Object SID.
     *
     * Fails upon no records returned.
     *
     * @throws ModelNotFoundException
     */
    public function findBySidOrFail(string $sid, array $selects = ['*']): Model
    {
        return $this->findByOrFail('objectsid', $sid, $selects);
    }

    /**
     * Adds a enabled filter to the current query.
     */
    public function whereEnabled(): static
    {
        return $this->notFilter(
            fn (self $query) => $query->whereDisabled()
        );
    }

    /**
     * Adds a disabled filter to the current query.
     */
    public function whereDisabled(): static
    {
        return $this->rawFilter(
            (new AccountControl)->setAccountIsDisabled()->filter()
        );
    }

    /**
     * Adds a 'where member' filter to the current query.
     */
    public function whereMember(string $dn, bool $nested = false): static
    {
        return $this->nestedMatchQuery(
            fn (string $attribute) => $this->whereEquals($attribute, $this->substituteBaseDn($dn)),
            'member',
            $nested
        );
    }

    /**
     * Adds an 'or where member' filter to the current query.
     */
    public function orWhereMember(string $dn, bool $nested = false): static
    {
        return $this->nestedMatchQuery(
            fn (string $attribute) => $this->orWhereEquals($attribute, $this->substituteBaseDn($dn)),
            'member',
            $nested
        );
    }

    /**
     * Adds a 'where member of' filter to the current query.
     */
    public function whereMemberOf(string $dn, bool $nested = false): static
    {
        return $this->nestedMatchQuery(
            fn (string $attribute) => $this->whereEquals($attribute, $this->substituteBaseDn($dn)),
            'memberof',
            $nested
        );
    }

    /**
     * Adds a 'where not member of' filter to the current query.
     */
    public function whereNotMemberof(string $dn, bool $nested = false): static
    {
        return $this->nestedMatchQuery(
            fn (string $attribute) => $this->whereNotEquals($attribute, $this->substituteBaseDn($dn)),
            'memberof',
            $nested
        );
    }

    /**
     * Adds an 'or where member of' filter to the current query.
     */
    public function orWhereMemberOf(string $dn, bool $nested = false): static
    {
        return $this->nestedMatchQuery(
            fn (string $attribute) => $this->orWhereEquals($attribute, $this->substituteBaseDn($dn)),
            'memberof',
            $nested
        );
    }

    /**
     * Adds a 'or where not member of' filter to the current query.
     */
    public function orWhereNotMemberof(string $dn, bool $nested = false): static
    {
        return $this->nestedMatchQuery(
            fn (string $attribute) => $this->orWhereNotEquals($attribute, $this->substituteBaseDn($dn)),
            'memberof',
            $nested
        );
    }

    /**
     * Adds a 'where manager' filter to the current query.
     */
    public function whereManager(string $dn, bool $nested = false): static
    {
        return $this->nestedMatchQuery(
            fn (string $attribute) => $this->whereEquals($attribute, $this->substituteBaseDn($dn)),
            'manager',
            $nested
        );
    }

    /**
     * Adds a 'where not manager' filter to the current query.
     */
    public function whereNotManager(string $dn, bool $nested = false): static
    {
        return $this->nestedMatchQuery(
            fn (string $attribute) => $this->whereNotEquals($attribute, $this->substituteBaseDn($dn)),
            'manager',
            $nested
        );
    }

    /**
     * Adds an 'or where manager' filter to the current query.
     */
    public function orWhereManager(string $dn, bool $nested = false): static
    {
        return $this->nestedMatchQuery(
            fn (string $attribute) => $this->orWhereEquals($attribute, $this->substituteBaseDn($dn)),
            'manager',
            $nested
        );
    }

    /**
     * Adds an 'or where not manager' filter to the current query.
     */
    public function orWhereNotManager(string $dn, bool $nested = false): static
    {
        return $this->nestedMatchQuery(
            fn (string $attribute) => $this->orWhereNotEquals($attribute, $this->substituteBaseDn($dn)),
            'manager',
            $nested
        );
    }

    /**
     * Execute the callback with a nested match attribute.
     */
    protected function nestedMatchQuery(Closure $callback, string $attribute, bool $nested = false): static
    {
        return $callback(
            $nested ? $this->makeNestedMatchAttribute($attribute) : $attribute
        );
    }

    /**
     * Make a "nested match" filter attribute for querying descendants.
     */
    protected function makeNestedMatchAttribute(string $attribute): string
    {
        return sprintf('%s:%s:', $attribute, LdapInterface::OID_MATCHING_RULE_IN_CHAIN);
    }
}
