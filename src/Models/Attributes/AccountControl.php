<?php

namespace LdapRecord\Models\Attributes;

use ReflectionClass;

class AccountControl
{
    public const SCRIPT = 1;

    public const ACCOUNTDISABLE = 2;

    public const HOMEDIR_REQUIRED = 8;

    public const LOCKOUT = 16;

    public const PASSWD_NOTREQD = 32;

    public const PASSWD_CANT_CHANGE = 64;

    public const ENCRYPTED_TEXT_PWD_ALLOWED = 128;

    public const TEMP_DUPLICATE_ACCOUNT = 256;

    public const NORMAL_ACCOUNT = 512;

    public const INTERDOMAIN_TRUST_ACCOUNT = 2048;

    public const WORKSTATION_TRUST_ACCOUNT = 4096;

    public const SERVER_TRUST_ACCOUNT = 8192;

    public const DONT_EXPIRE_PASSWORD = 65536;

    public const MNS_LOGON_ACCOUNT = 131072;

    public const SMARTCARD_REQUIRED = 262144;

    public const TRUSTED_FOR_DELEGATION = 524288;

    public const NOT_DELEGATED = 1048576;

    public const USE_DES_KEY_ONLY = 2097152;

    public const DONT_REQ_PREAUTH = 4194304;

    public const PASSWORD_EXPIRED = 8388608;

    public const TRUSTED_TO_AUTH_FOR_DELEGATION = 16777216;

    public const PARTIAL_SECRETS_ACCOUNT = 67108864;

    /**
     * The account control flag values.
     *
     * @var array<int, int>
     */
    protected array $values = [];

    /**
     * Constructor.
     */
    public function __construct(int $flag = null)
    {
        if (! is_null($flag)) {
            $this->apply($flag);
        }
    }

    /**
     * Get the value when casted to string.
     */
    public function __toString(): string
    {
        return (string) $this->getValue();
    }

    /**
     * Get the value when casted to int.
     */
    public function __toInt(): int
    {
        return $this->getValue();
    }

    /**
     * Add the flag to the account control values.
     */
    public function add(int $flag): static
    {
        // Use the value as a key so if the same value
        // is used, it will always be overwritten
        $this->values[$flag] = $flag;

        return $this;
    }

    /**
     * Remove the flag from the account control.
     */
    public function remove(int $flag): static
    {
        unset($this->values[$flag]);

        return $this;
    }

    /**
     * Extract and apply the flag.
     */
    public function apply(int $flag): void
    {
        $this->setValues($this->extractFlags($flag));
    }

    /**
     * Determine if the account control contains the given UAC flag(s).
     */
    public function has(int $flag): bool
    {
        // Here we will extract the given flag into an array
        // of possible flags. This will allow us to see if
        // our AccountControl object contains any of them.
        $flagsUsed = array_intersect(
            $this->extractFlags($flag),
            $this->values
        );

        return in_array($flag, $flagsUsed);
    }

    /**
     * Determine if the account control does not contain the given UAC flag(s).
     */
    public function doesntHave(int $flag): bool
    {
        return ! $this->has($flag);
    }

    /**
     * Generate an LDAP filter based on the current value.
     */
    public function filter(): string
    {
        return sprintf('(UserAccountControl:1.2.840.113556.1.4.803:=%s)', $this->getValue());
    }

    /**
     * The logon script will be run.
     */
    public function runLoginScript(): static
    {
        return $this->add(static::SCRIPT);
    }

    /**
     * The user account is locked.
     */
    public function accountIsLocked(): static
    {
        return $this->add(static::LOCKOUT);
    }

    /**
     * The user account is disabled.
     */
    public function accountIsDisabled(): static
    {
        return $this->add(static::ACCOUNTDISABLE);
    }

    /**
     * This is an account for users whose primary account is in another domain.
     *
     * This account provides user access to this domain, but not to any domain that
     * trusts this domain. This is sometimes referred to as a local user account.
     */
    public function accountIsTemporary(): static
    {
        return $this->add(static::TEMP_DUPLICATE_ACCOUNT);
    }

    /**
     * This is a default account type that represents a typical user.
     */
    public function accountIsNormal(): static
    {
        return $this->add(static::NORMAL_ACCOUNT);
    }

    /**
     * This is a permit to trust an account for a system domain that trusts other domains.
     */
    public function accountIsForInterdomain(): static
    {
        return $this->add(static::INTERDOMAIN_TRUST_ACCOUNT);
    }

    /**
     * This is a computer account for a computer that is running Microsoft
     * Windows NT 4.0 Workstation, Microsoft Windows NT 4.0 Server, Microsoft
     * Windows 2000 Professional, or Windows 2000 Server and is a member of this domain.
     */
    public function accountIsForWorkstation(): static
    {
        return $this->add(static::WORKSTATION_TRUST_ACCOUNT);
    }

    /**
     * This is a computer account for a domain controller that is a member of this domain.
     */
    public function accountIsForServer(): static
    {
        return $this->add(static::SERVER_TRUST_ACCOUNT);
    }

    /**
     * This is an MNS logon account.
     */
    public function accountIsMnsLogon(): static
    {
        return $this->add(static::MNS_LOGON_ACCOUNT);
    }

    /**
     * (Windows 2000/Windows Server 2003) This account does
     * not require Kerberos pre-authentication for logging on.
     */
    public function accountDoesNotRequirePreAuth(): static
    {
        return $this->add(static::DONT_REQ_PREAUTH);
    }

    /**
     * When this flag is set, it forces the user to log on by using a smart card.
     */
    public function accountRequiresSmartCard(): static
    {
        return $this->add(static::SMARTCARD_REQUIRED);
    }

    /**
     * (Windows Server 2008/Windows Server 2008 R2) The account is a read-only domain controller (RODC).
     *
     * This is a security-sensitive setting. Removing this setting from an RODC compromises security on that server.
     */
    public function accountIsReadOnly(): static
    {
        return $this->add(static::PARTIAL_SECRETS_ACCOUNT);
    }

    /**
     * The home folder is required.
     */
    public function homeFolderIsRequired(): static
    {
        return $this->add(static::HOMEDIR_REQUIRED);
    }

    /**
     * No password is required.
     */
    public function passwordIsNotRequired(): static
    {
        return $this->add(static::PASSWD_NOTREQD);
    }

    /**
     * The user cannot change the password. This is a permission on the user's object.
     *
     * For information about how to programmatically set this permission, visit the following link:
     *
     * @see http://msdn2.microsoft.com/en-us/library/aa746398.aspx
     */
    public function passwordCannotBeChanged(): static
    {
        return $this->add(static::PASSWD_CANT_CHANGE);
    }

    /**
     * Represents the password, which should never expire on the account.
     */
    public function passwordDoesNotExpire(): static
    {
        return $this->add(static::DONT_EXPIRE_PASSWORD);
    }

    /**
     * (Windows 2000/Windows Server 2003) The user's password has expired.
     */
    public function passwordIsExpired(): static
    {
        return $this->add(static::PASSWORD_EXPIRED);
    }

    /**
     * The user can send an encrypted password.
     */
    public function allowEncryptedTextPassword(): static
    {
        return $this->add(static::ENCRYPTED_TEXT_PWD_ALLOWED);
    }

    /**
     * When this flag is set, the service account (the user or computer account)
     * under which a service runs is trusted for Kerberos delegation.
     *
     * Any such service can impersonate a client requesting the service.
     *
     * To enable a service for Kerberos delegation, you must set this
     * flag on the userAccountControl property of the service account.
     */
    public function trustForDelegation(): static
    {
        return $this->add(static::TRUSTED_FOR_DELEGATION);
    }

    /**
     * (Windows 2000/Windows Server 2003) The account is enabled for delegation.
     *
     * This is a security-sensitive setting. Accounts that have this option enabled
     * should be tightly controlled. This setting lets a service that runs under the
     * account assume a client's identity and authenticate as that user to other remote
     * servers on the network.
     */
    public function trustToAuthForDelegation(): static
    {
        return $this->add(static::TRUSTED_TO_AUTH_FOR_DELEGATION);
    }

    /**
     * When this flag is set, the security context of the user is not delegated to a
     * service even if the service account is set as trusted for Kerberos delegation.
     */
    public function doNotTrustForDelegation(): static
    {
        return $this->add(static::NOT_DELEGATED);
    }

    /**
     * (Windows 2000/Windows Server 2003) Restrict this principal to
     * use only Data Encryption Standard (DES) encryption types for keys.
     */
    public function useDesKeyOnly(): static
    {
        return $this->add(static::USE_DES_KEY_ONLY);
    }

    /**
     * Get the account control value.
     */
    public function getValue(): int
    {
        return array_sum($this->values);
    }

    /**
     * Get the account control flag values.
     *
     * @return array<int, int>
     */
    public function getValues(): array
    {
        return $this->values;
    }

    /**
     * Set the account control values.
     *
     * @param  array<int, int>  $flags
     */
    public function setValues(array $flags): void
    {
        $this->values = $flags;
    }

    /**
     * Get all flags that are currently applied to the value.
     */
    public function getAppliedFlags(): array
    {
        $flags = $this->getAllFlags();

        $exists = [];

        foreach ($flags as $name => $flag) {
            if ($this->has($flag)) {
                $exists[$name] = $flag;
            }
        }

        return $exists;
    }

    /**
     * Get all possible account control flags.
     */
    public function getAllFlags(): array
    {
        return (new ReflectionClass(__CLASS__))->getConstants();
    }

    /**
     * Extracts the given flag into an array of flags used.
     */
    public function extractFlags(int $flag): array
    {
        $flags = [];

        for ($i = 0; $i <= 26; $i++) {
            if ($flag & (1 << $i)) {
                $flags[1 << $i] = 1 << $i;
            }
        }

        return $flags;
    }
}
