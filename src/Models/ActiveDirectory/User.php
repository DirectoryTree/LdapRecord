<?php

namespace LdapRecord\Models\ActiveDirectory;

use LdapRecord\Utilities;
use LdapRecord\LdapRecordException;
use LdapRecord\Models\Concerns\HasGroups;
use LdapRecord\Models\Concerns\CanAuthenticate;
use LdapRecord\Models\UserPasswordPolicyException;
use LdapRecord\Models\UserPasswordIncorrectException;
use Illuminate\Contracts\Auth\Authenticatable;

class User extends Entry implements Authenticatable
{
    use HasGroups, CanAuthenticate;

    /**
     * The object classes of the LDAP model.
     * 
     * @var array
     */
    public static $objectClasses = [
        'top',
        'person',
        'organizationalperson',
        'user',
    ];

    /**
     * The groups relationship.
     *
     * Retrieves groups that the current user is apart of.
     *
     * @return \LdapRecord\Models\Relations\HasMany
     */
    public function groups()
    {
        return $this->hasMany(Group::class, 'member');
    }

    /**
     * Retrieves the primary group of the current user.
     *
     * @return Group|null
     */
    public function getPrimaryGroup()
    {
        $groupSid = preg_replace('/\d+$/', $this->getFirstAttribute('primarygroupid'), $this->getConvertedSid());

        $model = reset($this->groups()->getRelated());

        return $this->newQueryWithoutScopes()->setModel(new $model)->findBySid($groupSid);
    }

    /**
     * Sets the password on the current user.
     *
     * @param string $password
     *
     * @throws \Exception When no SSL or TLS secured connection is present.
     *
     * @return \LdapRecord\Models\ActiveDirectory\User
     */
    public function setPassword($password)
    {
        $this->validateSecureConnection();

        $encodedPassword = Utilities::encodePassword($password);

        // If the record exists, we need to add a batch replace
        // modification, otherwise we'll receive a "type or
        // value" exists exception from our LDAP server.
        if ($this->exists) {
            return $this->addModification(
                $this->newBatchModification(
                    'unicodepwd',
                    LDAP_MODIFY_BATCH_REPLACE,
                    [$encodedPassword]
                )
            );
        }
        // Otherwise, we are creating a new record
        // and we can set the attribute normally.
        else {
            return $this->setFirstAttribute('unicodepwd', $encodedPassword);
        }
    }

    /**
     * Change the password of the current user. This must be performed over SSL / TLS.
     *
     * Throws an exception on failure.
     *
     * @param string $oldPassword      The new password
     * @param string $newPassword      The old password
     * @param bool   $replaceNotRemove Alternative password change method. Set to true if you're receiving 'CONSTRAINT'
     *                                 errors.
     *
     * @throws UserPasswordPolicyException    When the new password does not match your password policy.
     * @throws UserPasswordIncorrectException When the old password is incorrect.
     * @throws LdapRecordException            When an unknown cause of failure occurs.
     *
     * @return true
     */
    public function changePassword($oldPassword, $newPassword, $replaceNotRemove = false)
    {
        $attribute = 'unicodepwd';

        $modifications = [];

        if ($replaceNotRemove) {
            $modifications[] = $this->newBatchModification(
                $attribute,
                LDAP_MODIFY_BATCH_REPLACE,
                [Utilities::encodePassword($newPassword)]
            );
        } else {
            // Create batch modification for removing the old password.
            $modifications[] = $this->newBatchModification(
                $attribute,
                LDAP_MODIFY_BATCH_REMOVE,
                [Utilities::encodePassword($oldPassword)]
            );

            // Create batch modification for adding the new password.
            $modifications[] = $this->newBatchModification(
                $attribute,
                LDAP_MODIFY_BATCH_ADD,
                [Utilities::encodePassword($newPassword)]
            );
        }

        foreach ($modifications as $modification) {
            $this->addModification($modification);
        }

        try {
            return $this->update();
        } catch (\Exception $ex) {
            $connection = $this->newQuery()->getConnection();

            $code = $connection->getExtendedErrorCode();

            switch ($code) {
                case '0000052D':
                    throw new UserPasswordPolicyException(
                        "Error: $code. Your new password does not match the password policy."
                    );
                case '00000056':
                    throw new UserPasswordIncorrectException(
                        "Error: $code. Your old password is incorrect."
                    );
                default:
                    throw new LdapRecordException($connection->getExtendedError());
            }
        }
    }
}
