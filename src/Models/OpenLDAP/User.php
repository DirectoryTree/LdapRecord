<?php

namespace LdapRecord\Models\OpenLDAP;

use Illuminate\Contracts\Auth\Authenticatable;
use LdapRecord\LdapRecordException;
use LdapRecord\Models\Concerns\CanAuthenticate;
use LdapRecord\Models\Concerns\HasPassword;
use LdapRecord\Models\Relations\HasMany;

class User extends Entry implements Authenticatable
{
    use CanAuthenticate;
    use HasPassword;

    /**
     * The password's attribute name.
     */
    protected string $passwordAttribute = 'userpassword';

    /**
     * The password's hash method.
     */
    protected string $passwordHashMethod = 'ssha';

    /**
     * The object classes of the LDAP model.
     */
    public static array $objectClasses = [
        'top',
        'person',
        'organizationalperson',
        'inetorgperson',
    ];

    /**
     * Change the user's password.
     *
     * @throws LdapRecordException
     */
    public function changePassword(string $oldPassword, string $newPassword): void
    {
        $this->assertSecureConnection();

        if (! $this->exists || ! $this->getDn()) {
            throw new LdapRecordException(
                'A password change requires an existing model with a distinguished name.'
            );
        }

        $this->getConnection()->changePassword(
            $this->getDn(), $oldPassword, $newPassword
        );
    }

    /**
     * Get the unique identifier for the user.
     */
    public function getAuthIdentifier(): string
    {
        return $this->getFirstAttribute($this->guidKey);
    }

    /**
     * The groups relationship.
     */
    public function groups(): HasMany
    {
        return $this->hasMany(Group::class, 'uniquemember');
    }
}
