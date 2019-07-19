<?php

namespace LdapRecord\Models;

use DateTime;
use LdapRecord\Utilities;
use LdapRecord\LdapRecordException;
use LdapRecord\Models\Types\ActiveDirectory;
use LdapRecord\Models\Attributes\AccountControl;
use LdapRecord\Models\Attributes\TSPropertyArray;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Class User.
 *
 * Represents an LDAP user.
 */
class User extends Entry implements Authenticatable
{
    use Concerns\HasMemberOf,
        Concerns\HasDescription,
        Concerns\HasUserProperties,
        Concerns\HasLastLogonAndLogOff,
        Concerns\HasUserAccountControl;

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
     * Get the name of the unique identifier for the user.
     *
     * @return string
     */
    public function getAuthIdentifierName()
    {
        return $this->guidKey;
    }

    /**
     * Get the unique identifier for the user.
     *
     * @return mixed
     */
    public function getAuthIdentifier()
    {
        return $this->getConvertedGuid();
    }

    /**
     * Get the password for the user.
     *
     * @return string
     */
    public function getAuthPassword()
    {
    }

    /**
     * Get the token value for the "remember me" session.
     *
     * @return string
     */
    public function getRememberToken()
    {
    }

    /**
     * Set the token value for the "remember me" session.
     *
     * @param string $value
     *
     * @return void
     */
    public function setRememberToken($value)
    {
    }

    /**
     * Get the column name for the "remember me" token.
     *
     * @return string
     */
    public function getRememberTokenName()
    {
    }

    /**
     * Returns the department number.
     *
     * @return string
     */
    public function getDepartmentNumber()
    {
        return $this->getFirstAttribute('departmentnumber');
    }

    /**
     * Sets the department number.
     *
     * @param string $number
     *
     * @return $this
     */
    public function setDepartmentNumber($number)
    {
        return $this->setFirstAttribute('departmentnumber', $number);
    }

    /**
     * Returns the users info.
     *
     * @return mixed
     */
    public function getInfo()
    {
        return $this->getFirstAttribute('info');
    }

    /**
     * Sets the users info.
     *
     * @param string $info
     *
     * @return $this
     */
    public function setInfo($info)
    {
        return $this->setFirstAttribute('info', $info);
    }

    /**
     * Returns the users physical delivery office name.
     *
     * @return string
     */
    public function getPhysicalDeliveryOfficeName()
    {
        return $this->getFirstAttribute('physicaldeliveryofficename');
    }

    /**
     * Sets the users physical delivery office name.
     *
     * @param string $deliveryOffice
     *
     * @return $this
     */
    public function setPhysicalDeliveryOfficeName($deliveryOffice)
    {
        return $this->setFirstAttribute('physicaldeliveryofficename', $deliveryOffice);
    }

    /**
     * Returns the users locale.
     *
     * @return string
     */
    public function getLocale()
    {
        return $this->getFirstAttribute('l');
    }

    /**
     * Sets the users locale.
     *
     * @param string $locale
     *
     * @return $this
     */
    public function setLocale($locale)
    {
        return $this->setFirstAttribute('l', $locale);
    }

    /**
     * Returns the users company.
     *
     * @link https://msdn.microsoft.com/en-us/library/ms675457(v=vs.85).aspx
     *
     * @return string
     */
    public function getCompany()
    {
        return $this->getFirstAttribute('company');
    }

    /**
     * Sets the users company.
     *
     * @param string $company
     *
     * @return $this
     */
    public function setCompany($company)
    {
        return $this->setFirstAttribute('company', $company);
    }

    /**
     * Returns the users mailbox store DN.
     *
     * @link https://msdn.microsoft.com/en-us/library/aa487565(v=exchg.65).aspx
     *
     * @return string
     */
    public function getHomeMdb()
    {
        return $this->getFirstAttribute('homemdb');
    }

    /**
     * Sets the users home drive.
     *
     * @link https://msdn.microsoft.com/en-us/library/ms676191(v=vs.85).aspx
     *
     * @return $this
     */
    public function setHomeDrive($drive)
    {
        return $this->setAttribute('homedrive', $drive);
    }

    /**
     * Specifies the drive letter to which to map the UNC path specified by homeDirectory.
     *
     * @link https://msdn.microsoft.com/en-us/library/ms676191(v=vs.85).aspx
     *
     * @return string|null
     */
    public function getHomeDrive()
    {
        return $this->getFirstAttribute('homedrive');
    }

    /**
     * Sets the users home directory.
     *
     * @link https://msdn.microsoft.com/en-us/library/ms676190(v=vs.85).aspx
     *
     * @param string $directory
     *
     * @return $this
     */
    public function setHomeDirectory($directory)
    {
        return $this->setAttribute('homedirectory', $directory);
    }

    /**
     * The home directory for the account.
     *
     * @link https://msdn.microsoft.com/en-us/library/ms676190(v=vs.85).aspx
     *
     * @return string|null
     */
    public function getHomeDirectory()
    {
        return $this->getFirstAttribute('homedirectory');
    }

    /**
     * The user's main home phone number.
     *
     * @link https://docs.microsoft.com/en-us/windows/desktop/ADSchema/a-homephone
     *
     * @return string|null
     */
    public function getHomePhone()
    {
        return $this->getFirstAttribute('homephone');
    }

    /**
     * Returns the users principal name.
     *
     * This is usually their email address.
     *
     * @link https://msdn.microsoft.com/en-us/library/ms680857(v=vs.85).aspx
     *
     * @return string
     */
    public function getUserPrincipalName()
    {
        return $this->getFirstAttribute('userprincipalname');
    }

    /**
     * Sets the users user principal name.
     *
     * @param string $userPrincipalName
     *
     * @return $this
     */
    public function setUserPrincipalName($userPrincipalName)
    {
        return $this->setFirstAttribute('userprincipalname', $userPrincipalName);
    }

    /**
     * Returns an array of workstations the user is assigned to.
     *
     * @return array
     */
    public function getUserWorkstations()
    {
        $workstations = $this->getFirstAttribute('userworkstations');

        return array_filter(explode(',', $workstations));
    }

    /**
     * Sets the workstations the user can login to.
     *
     * @param string|array $workstations The names of the workstations the user can login to.
     *                                   Must be an array of names, or a comma separated
     *                                   list of names.
     *
     * @return $this
     */
    public function setUserWorkstations($workstations = [])
    {
        if (is_array($workstations)) {
            $workstations = implode(',', $workstations);
        }

        return $this->setFirstAttribute('userworkstations', $workstations);
    }

    /**
     * Returns the users script path if the user has one.
     *
     * @link https://msdn.microsoft.com/en-us/library/ms679656(v=vs.85).aspx
     *
     * @return string
     */
    public function getScriptPath()
    {
        return $this->getFirstAttribute('scriptpath');
    }

    /**
     * Sets the users script path.
     *
     * @param string $path
     *
     * @return $this
     */
    public function setScriptPath($path)
    {
        return $this->setFirstAttribute('scriptpath', $path);
    }

    /**
     * Returns the users bad password count.
     *
     * @return string
     */
    public function getBadPasswordCount()
    {
        return $this->getFirstAttribute('badpwdcount');
    }

    /**
     * Returns the users bad password time.
     *
     * @return string
     */
    public function getBadPasswordTime()
    {
        return $this->getFirstAttribute('badpasswordtime');
    }

    /**
     * Returns the bad password time unix timestamp.
     *
     * @return float|null
     */
    public function getBadPasswordTimestamp()
    {
        if ($time = $this->getBadPasswordTime()) {
            return Utilities::convertWindowsTimeToUnixTime($time);
        }
    }

    /**
     * Returns the formatted timestamp of the bad password date.
     *
     * @throws \Exception
     *
     * @return string|null
     */
    public function getBadPasswordDate()
    {
        if ($timestamp = $this->getBadPasswordTimestamp()) {
            return (new DateTime())->setTimestamp($timestamp)->format($this->dateFormat);
        }
    }

    /**
     * Returns the time when the users password was set last.
     *
     * @return string
     */
    public function getPasswordLastSet()
    {
        return $this->getFirstAttribute('pwdlastset');
    }

    /**
     * Returns the password last set unix timestamp.
     *
     * @return float|null
     */
    public function getPasswordLastSetTimestamp()
    {
        if ($time = $this->getPasswordLastSet()) {
            return Utilities::convertWindowsTimeToUnixTime($time);
        }
    }

    /**
     * Returns the formatted timestamp of the password last set date.
     *
     * @throws \Exception
     *
     * @return string|null
     */
    public function getPasswordLastSetDate()
    {
        if ($timestamp = $this->getPasswordLastSetTimestamp()) {
            return (new DateTime())->setTimestamp($timestamp)->format($this->dateFormat);
        }
    }

    /**
     * Returns the users lockout time.
     *
     * @return string
     */
    public function getLockoutTime()
    {
        return $this->getFirstAttribute('lockouttime');
    }

    /**
     * Returns the users lockout unix timestamp.
     *
     * @return float|null
     */
    public function getLockoutTimestamp()
    {
        if ($time = $this->getLockoutTime()) {
            return Utilities::convertWindowsTimeToUnixTime($time);
        }
    }

    /**
     * Returns the formatted timestamp of the lockout date.
     *
     * @throws \Exception
     *
     * @return string|null
     */
    public function getLockoutDate()
    {
        if ($timestamp = $this->getLockoutTimestamp()) {
            return (new DateTime())->setTimestamp($timestamp)->format($this->dateFormat);
        }
    }

    /**
     * Clears the accounts lockout time, unlocking the account.
     *
     * @return $this
     */
    public function setClearLockoutTime()
    {
        return $this->setFirstAttribute('lockouttime', 0);
    }

    /**
     * Returns the users profile file path.
     *
     * @return string
     */
    public function getProfilePath()
    {
        return $this->getFirstAttribute('profilepath');
    }

    /**
     * Sets the users profile path.
     *
     * @param string $path
     *
     * @return $this
     */
    public function setProfilePath($path)
    {
        return $this->setFirstAttribute('profilepath', $path);
    }

    /**
     * Returns the users legacy exchange distinguished name.
     *
     * @return string
     */
    public function getLegacyExchangeDn()
    {
        return $this->getFirstAttribute('legacyexchangedn');
    }

    /**
     * Sets the users account expiry date.
     *
     * If no expiry time is given, the account is set to never expire.
     *
     * @link https://msdn.microsoft.com/en-us/library/ms675098(v=vs.85).aspx
     *
     * @param float $expiryTime
     *
     * @return $this
     */
    public function setAccountExpiry($expiryTime)
    {
        $time = is_null($expiryTime) ? '9223372036854775807' : (string) Utilities::convertUnixTimeToWindowsTime($expiryTime);

        return $this->setFirstAttribute('accountexpires', $time);
    }

    /**
     * Returns an array of address book DNs
     * that the user is listed to be shown in.
     *
     * @return array
     */
    public function getShowInAddressBook()
    {
        return $this->getAttribute('showinaddressbook');
    }

    /**
     * Returns the users thumbnail photo base 64 encoded.
     *
     * Suitable for inserting into an HTML image element.
     *
     * @return string|null
     */
    public function getThumbnailEncoded()
    {
        if ($data = base64_decode($this->getThumbnail(), $strict = true)) {
            // In case we don't have the file info extension enabled,
            // we'll set the jpeg mime type as default.
            $mime = 'image/jpeg';

            $image = base64_encode($data);

            if (function_exists('finfo_open')) {
                $finfo = finfo_open();

                $mime = finfo_buffer($finfo, $data, FILEINFO_MIME_TYPE);

                return "data:$mime;base64,$image";
            }

            return "data:$mime;base64,$image";
        }
    }

    /**
     * Returns the users thumbnail photo.
     *
     * @return mixed
     */
    public function getThumbnail()
    {
        return $this->getFirstAttribute('thumbnailphoto');
    }

    /**
     * Sets the users thumbnail photo.
     *
     * @param string $data
     * @param bool   $encode
     *
     * @return $this
     */
    public function setThumbnail($data, $encode = true)
    {
        if ($encode && !base64_decode($data, $strict = true)) {
            // If the string we're given is not base 64 encoded, then
            // we will encode it before setting it on the user.
            $data = base64_encode($data);
        }

        return $this->setAttribute('thumbnailphoto', $data);
    }

    /**
     * Returns the users JPEG photo.
     *
     * @return null|string
     */
    public function getJpegPhotoEncoded()
    {
        $jpeg = $this->getJpegPhoto();

        return is_null($jpeg) ? $jpeg : 'data:image/jpeg;base64,'.base64_encode($jpeg);
    }

    /**
     * Returns the users JPEG photo.
     *
     * @return mixed
     */
    public function getJpegPhoto()
    {
        return $this->getFirstAttribute('jpegphoto');
    }

    /**
     * Sets the users JPEG photo.
     *
     * @param string $string
     *
     * @return $this
     */
    public function setJpegPhoto($string)
    {
        if (!base64_decode($string, $strict = true)) {
            $string = base64_encode($string);
        }

        return $this->setAttribute('jpegphoto', $string);
    }

    /**
     * Return the employee ID.
     *
     * @return string
     */
    public function getEmployeeId()
    {
        return $this->getFirstAttribute('employeeid');
    }

    /**
     * Sets the employee ID.
     *
     * @param string $employeeId
     *
     * @return $this
     */
    public function setEmployeeId($employeeId)
    {
        return $this->setFirstAttribute('employeeid', $employeeId);
    }

    /**
     * Returns the employee type.
     *
     * @return string|null
     */
    public function getEmployeeType()
    {
        return $this->getFirstAttribute('employeetype');
    }

    /**
     * Sets the employee type.
     *
     * @param string $type
     *
     * @return $this
     */
    public function setEmployeeType($type)
    {
        return $this->setFirstAttribute('employeetype', $type);
    }

    /**
     * Returns the employee number.
     *
     * @return string
     */
    public function getEmployeeNumber()
    {
        return $this->getFirstAttribute('employeenumber');
    }

    /**
     * Sets the employee number.
     *
     * @param string $number
     *
     * @return $this
     */
    public function setEmployeeNumber($number)
    {
        return $this->setFirstAttribute('employeenumber', $number);
    }

    /**
     * Returns the room number.
     *
     * @return string
     */
    public function getRoomNumber()
    {
        return $this->getFirstAttribute('roomnumber');
    }

    /**
     * Sets the room number.
     *
     * @param string $number
     *
     * @return $this
     */
    public function setRoomNumber($number)
    {
        return $this->setFirstAttribute('roomnumber', $number);
    }

    /**
     * Return the personal title.
     *
     * @return $this
     */
    public function getPersonalTitle()
    {
        return $this->getFirstAttribute('personaltitle');
    }

    /**
     * Sets the personal title.
     *
     * @param string $personalTitle
     *
     * @return $this
     */
    public function setPersonalTitle($personalTitle)
    {
        return $this->setFirstAttribute('personaltitle', $personalTitle);
    }

    /**
     * Return the user parameters.
     *
     * @return TSPropertyArray
     */
    public function getUserParameters()
    {
        return new TSPropertyArray($this->getFirstAttribute('userparameters'));
    }

    /**
     * Sets the user parameters.
     *
     * @param TSPropertyArray $userParameters
     *
     * @return $this
     */
    public function setUserParameters(TSPropertyArray $userParameters)
    {
        return $this->setFirstAttribute('userparameters', $userParameters->toBinary());
    }

    /**
     * Retrieves the primary group of the current user.
     *
     * @return Model|bool
     */
    public function getPrimaryGroup()
    {
        $groupSid = preg_replace('/\d+$/', $this->getPrimaryGroupId(), $this->getConvertedSid());

        return $this->query->newInstance()->findBySid($groupSid);
    }

    /**
     * Sets the password on the current user.
     *
     * @param string $password
     *
     * @throws LdapRecordException When no SSL or TLS secured connection is present.
     *
     * @return $this
     */
    public function setPassword($password)
    {
        $this->validateSecureConnection();

        $encodedPassword = Utilities::encodePassword($password);

        if ($this->exists) {
            // If the record exists, we need to add a batch replace
            // modification, otherwise we'll receive a "type or
            // value" exists exception from our LDAP server.
            return $this->addModification(
                $this->newBatchModification(
                    'unicodepwd',
                    LDAP_MODIFY_BATCH_REPLACE,
                    [$encodedPassword]
                )
            );
        } else {
            // Otherwise, we are creating a new record
            // and we can set the attribute normally.
            return $this->setFirstAttribute(
                'unicodepwd',
                $encodedPassword
            );
        }
    }

    /**
     * Sets the option to force the password change at the next logon.
     *
     * Does not work if the "Password never expires" option is enabled.
     *
     * @return $this
     */
    public function setEnableForcePasswordChange()
    {
        return $this->setFirstAttribute('pwdlastset', 0);
    }

    /**
     * Sets the option to disable forcing a password change at the next logon.
     *
     * @return $this
     */
    public function setDisableForcePasswordChange()
    {
        return $this->setFirstAttribute('pwdlastset', -1);
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
        $this->validateSecureConnection();

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

        // Add the modifications.
        foreach ($modifications as $modification) {
            $this->addModification($modification);
        }

        $result = @$this->update();

        if (!$result) {
            // If the user failed to update, we'll see if we can
            // figure out why by retrieving the extended error.
            $error = $this->query->getConnection()->getExtendedError();
            $code = $this->query->getConnection()->getExtendedErrorCode();

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
                    throw new LdapRecordException($error);
            }
        }

        return $result;
    }

    /**
     * Return true / false if LDAP User is active (enabled & not expired).
     *
     * @return bool
     */
    public function isActive()
    {
        return $this->isEnabled() && !$this->isExpired();
    }

    /**
     * Return true / false if the LDAP User is expired.
     *
     * @param DateTime $date Optional date
     *
     * @return bool
     */
    public function isExpired(DateTime $date = null)
    {
        // Here we'll determine if the account expires by checking is expiration date.
        if ($expirationDate = $this->expirationDate()) {
            $date = $date ?: new DateTime();

            return $expirationDate <= $date;
        }

        // The account has no expiry date.
        return false;
    }

    /**
     * Return the expiration date of the user account.
     *
     * @throws \Exception
     *
     * @return DateTime|null
     */
    public function expirationDate()
    {
        $accountExpiry = $this->getAccountExpiry();

        // If the account expiry is zero or the expiry is equal to
        // ActiveDirectory's 'never expire' value,
        // then we'll return null here.
        if ($accountExpiry == 0 || $accountExpiry == '9223372036854775807') {
            return;
        }

        $unixTime = Utilities::convertWindowsTimeToUnixTime($accountExpiry);

        return (new DateTime())->setTimestamp($unixTime);
    }

    /**
     * Returns the users account expiry date.
     *
     * @return string
     */
    public function getAccountExpiry()
    {
        return $this->getFirstAttribute('accountexpires');
    }

    /**
     * Returns true / false if the users password is expired.
     *
     * @throws \LdapRecord\Models\ModelNotFoundException When the RootDSE cannot be found.
     *
     * @return bool
     */
    public function passwordExpired()
    {
        // First we'll check the users userAccountControl to see if
        // it contains the 'password does not expire' flag.
        if ($this->getUserAccountControlObject()->has(AccountControl::DONT_EXPIRE_PASSWORD)) {
            return false;
        }

        $lastSet = (int) $this->getPasswordLastSet();

        if ($lastSet === 0) {
            // If the users last set time is zero, the password has
            // been manually expired by an administrator.
            return true;
        }

        // We will check if we're our model is from ActiveDirectory to retrieve
        // the max password age, as this is an AD-only feature.
        if ($this instanceof ActiveDirectory) {
            $query = $this->newQueryWithoutScopes();

            // We need to get the root domain object to be able to
            // retrieve the max password age on the domain.
            $rootDomainObject = $query->select('maxpwdage')
                ->whereHas('objectclass')
                ->firstOrFail();

            $maxPasswordAge = $rootDomainObject->getFirstAttribute('maxpwdage');

            if (empty($maxPasswordAge)) {
                // There is not a max password age set on the LDAP server.
                return false;
            }

            // convert from 100 nanosecond ticks to seconds
            $maxPasswordAgeSeconds = $maxPasswordAge / 10000000;

            $lastSetUnixEpoch = Utilities::convertWindowsTimeToUnixTime($lastSet);
            $passwordExpiryTime = $lastSetUnixEpoch - $maxPasswordAgeSeconds;

            $expiresAt = (new DateTime())->setTimestamp($passwordExpiryTime);

            // If our current time is greater than the users password
            // expiry time, the users password has expired.
            return (new DateTime())->getTimestamp() >= $expiresAt->getTimestamp();
        }

        return false;
    }
}
