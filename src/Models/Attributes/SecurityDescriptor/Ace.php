<?php

namespace LdapRecord\Models\Attributes\SecurityDescriptor;

use InvalidArgumentException;
use LdapRecord\Models\Attributes\Guid;
use LdapRecord\Models\Attributes\Sid;
use LogicException;

/**
 * An access control entry in a Windows security descriptor.
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
class Ace
{
    public const ACCESS_ALLOWED = 0x00;

    public const ACCESS_DENIED = 0x01;

    public const SYSTEM_AUDIT = 0x02;

    public const ACCESS_ALLOWED_OBJECT = 0x05;

    public const ACCESS_DENIED_OBJECT = 0x06;

    public const SYSTEM_AUDIT_OBJECT = 0x07;

    public const OBJECT_TYPE_PRESENT = 0x01;

    public const INHERITED_OBJECT_TYPE_PRESENT = 0x02;

    public const OBJECT_INHERIT = 0x01;

    public const CONTAINER_INHERIT = 0x02;

    public const NO_PROPAGATE_INHERIT = 0x04;

    public const INHERIT_ONLY = 0x08;

    public const INHERITED = 0x10;

    public const SUCCESSFUL_ACCESS = 0x40;

    public const FAILED_ACCESS = 0x80;

    public const CREATE_CHILD = 0x00000001;

    public const DELETE_CHILD = 0x00000002;

    public const LIST_CHILDREN = 0x00000004;

    public const SELF_WRITE = 0x00000008;

    public const READ_PROPERTY = 0x00000010;

    public const WRITE_PROPERTY = 0x00000020;

    public const DELETE_TREE = 0x00000040;

    public const LIST_OBJECT = 0x00000080;

    public const CONTROL_ACCESS = 0x00000100;

    public const DELETE = 0x00010000;

    public const READ_CONTROL = 0x00020000;

    public const WRITE_DACL = 0x00040000;

    public const WRITE_OWNER = 0x00080000;

    public const GENERIC_ALL = 0x10000000;

    public const GENERIC_EXECUTE = 0x20000000;

    public const GENERIC_WRITE = 0x40000000;

    public const GENERIC_READ = 0x80000000;

    public const CHANGE_PASSWORD = 'ab721a53-1e2f-11d0-9819-00aa0040529b';

    public const RESET_PASSWORD = '00299570-246d-11d0-a768-00aa006e0529';

    protected int $flags = 0;

    protected int $rights = 0;

    protected ?Sid $trustee = null;

    protected ?Guid $objectType = null;

    protected ?Guid $inheritedObjectType = null;

    protected int $objectFlags = 0;

    protected string $applicationData = '';

    protected ?string $opaque = null;

    /**
     * Constructor.
     */
    public function __construct(
        protected int $type = self::ACCESS_ALLOWED,
    ) {}

    /**
     * Create an entry granting the given rights to a trustee.
     */
    public static function allow(Sid|string $trustee, int $rights = 0): static
    {
        return (new static(static::ACCESS_ALLOWED))->setTrustee($trustee)->setRights($rights);
    }

    /**
     * Create an entry denying the given rights to a trustee.
     */
    public static function deny(Sid|string $trustee, int $rights = 0): static
    {
        return (new static(static::ACCESS_DENIED))->setTrustee($trustee)->setRights($rights);
    }

    /**
     * Create an entry auditing access by a trustee.
     */
    public static function audit(Sid|string $trustee, int $rights = 0): static
    {
        return (new static(static::SYSTEM_AUDIT))->setTrustee($trustee)->setRights($rights);
    }

    /**
     * Parse an entry, retaining unrecognized types without interpreting their data.
     */
    public static function fromBinary(string $binary): static
    {
        if (strlen($binary) < 4) {
            throw new InvalidArgumentException('The access control entry header is incomplete.');
        }

        $header = unpack('Ctype/Cflags/vsize', $binary);

        if ($header['size'] !== strlen($binary) || $header['size'] % 4 !== 0) {
            throw new InvalidArgumentException('The access control entry size is invalid.');
        }

        $ace = (new static($header['type']))->setFlags($header['flags']);

        if (! $ace->isSupported()) {
            $ace->opaque = substr($binary, 4);

            return $ace;
        }

        if (strlen($binary) < 8) {
            throw new InvalidArgumentException('The access control entry rights are missing.');
        }

        $ace->rights = unpack('Vrights', $binary, 4)['rights'];
        $offset = 8;

        if ($ace->isObjectAce()) {
            if (strlen($binary) < 12) {
                throw new InvalidArgumentException('The object access control entry flags are missing.');
            }

            $ace->objectFlags = unpack('Vflags', $binary, $offset)['flags'];
            $offset += 4;

            foreach ([static::OBJECT_TYPE_PRESENT => 'objectType', static::INHERITED_OBJECT_TYPE_PRESENT => 'inheritedObjectType'] as $flag => $property) {
                if ($ace->objectFlags & $flag) {
                    if (strlen($binary) < $offset + 16) {
                        throw new InvalidArgumentException('The object access control entry GUID is incomplete.');
                    }

                    $ace->{$property} = new Guid(substr($binary, $offset, 16));
                    $offset += 16;
                }
            }
        }

        if (strlen($binary) < $offset + 8) {
            throw new InvalidArgumentException('The access control entry trustee is missing.');
        }

        $length = 8 + ord($binary[$offset + 1]) * 4;

        if (strlen($binary) < $offset + $length) {
            throw new InvalidArgumentException('The access control entry trustee is incomplete.');
        }

        $ace->trustee = new Sid(substr($binary, $offset, $length));
        $ace->applicationData = substr($binary, $offset + $length);

        return $ace;
    }

    /**
     * Get the entry type.
     */
    public function getType(): int
    {
        return $this->type;
    }

    /**
     * Set the entry type without discarding object-specific data.
     */
    public function setType(int $type): static
    {
        $this->assertEditable();

        if (! in_array($type, [static::ACCESS_ALLOWED, static::ACCESS_DENIED, static::SYSTEM_AUDIT, static::ACCESS_ALLOWED_OBJECT, static::ACCESS_DENIED_OBJECT, static::SYSTEM_AUDIT_OBJECT], true)) {
            throw new InvalidArgumentException('The access control entry type is unsupported.');
        }

        if (in_array($type, [static::ACCESS_ALLOWED, static::ACCESS_DENIED, static::SYSTEM_AUDIT], true)
            && ($this->objectFlags || $this->objectType || $this->inheritedObjectType)) {
            throw new LogicException('Remove the object GUIDs and flags before selecting an ordinary entry type.');
        }

        $this->type = $type;

        return $this;
    }

    /**
     * Get the inheritance and audit flags.
     */
    public function getFlags(): int
    {
        return $this->flags;
    }

    /**
     * Set the inheritance and audit flags.
     */
    public function setFlags(int $flags): static
    {
        if ($flags < 0 || $flags > 0xFF) {
            throw new InvalidArgumentException('The access control entry flags must fit in one byte.');
        }

        $this->flags = $flags;

        return $this;
    }

    /**
     * Get the access mask.
     */
    public function getRights(): int
    {
        $this->assertEditable();

        return $this->rights;
    }

    /**
     * Set the access mask.
     */
    public function setRights(int $rights): static
    {
        $this->assertEditable();

        if ($rights < 0 || $rights > 0xFFFFFFFF) {
            throw new InvalidArgumentException('The access mask must be an unsigned 32-bit integer.');
        }

        $this->rights = $rights;

        return $this;
    }

    /**
     * Get the trustee.
     */
    public function getTrustee(): ?Sid
    {
        return $this->trustee;
    }

    /**
     * Set the trustee.
     */
    public function setTrustee(Sid|string $trustee): static
    {
        $this->assertEditable();
        $this->trustee = $trustee instanceof Sid ? $trustee : new Sid($trustee);

        return $this;
    }

    /**
     * Get the object type GUID.
     */
    public function getObjectType(): ?Guid
    {
        return $this->objectType;
    }

    /**
     * Set the object type GUID, promoting an ordinary entry to an object entry.
     */
    public function setObjectType(Guid|string|null $type): static
    {
        return $this->setObjectGuid('objectType', $type, static::OBJECT_TYPE_PRESENT);
    }

    /**
     * Get the inherited object type GUID.
     */
    public function getInheritedObjectType(): ?Guid
    {
        return $this->inheritedObjectType;
    }

    /**
     * Set the inherited object type GUID.
     */
    public function setInheritedObjectType(Guid|string|null $type): static
    {
        return $this->setObjectGuid('inheritedObjectType', $type, static::INHERITED_OBJECT_TYPE_PRESENT);
    }

    /**
     * Get the object flags, including any unrecognized bits.
     */
    public function getObjectFlags(): int
    {
        return $this->objectFlags;
    }

    /**
     * Get the application data following the trustee.
     */
    public function getApplicationData(): string
    {
        $this->assertEditable();

        return $this->applicationData;
    }

    /**
     * Set the application data following the trustee.
     */
    public function setApplicationData(string $data): static
    {
        $this->assertEditable();
        $this->applicationData = $data;

        return $this;
    }

    /**
     * Determine whether this entry has a supported binary layout.
     */
    public function isSupported(): bool
    {
        return in_array($this->type, [static::ACCESS_ALLOWED, static::ACCESS_DENIED, static::SYSTEM_AUDIT, static::ACCESS_ALLOWED_OBJECT, static::ACCESS_DENIED_OBJECT, static::SYSTEM_AUDIT_OBJECT], true);
    }

    /**
     * Determine whether this is an object-specific entry.
     */
    public function isObjectAce(): bool
    {
        return in_array($this->type, [static::ACCESS_ALLOWED_OBJECT, static::ACCESS_DENIED_OBJECT, static::SYSTEM_AUDIT_OBJECT], true);
    }

    /**
     * Determine whether this is an allow entry.
     */
    public function isAllowAce(): bool
    {
        return in_array($this->type, [static::ACCESS_ALLOWED, static::ACCESS_ALLOWED_OBJECT], true);
    }

    /**
     * Determine whether this is a deny entry.
     */
    public function isDenyAce(): bool
    {
        return in_array($this->type, [static::ACCESS_DENIED, static::ACCESS_DENIED_OBJECT], true);
    }

    /**
     * Determine whether this entry was inherited.
     */
    public function isInherited(): bool
    {
        return (bool) ($this->flags & static::INHERITED);
    }

    /**
     * Encode the entry without changing its type or order.
     */
    public function toBinary(): string
    {
        if ($this->opaque !== null) {
            $body = $this->opaque;
        } else {
            $this->assertEditable();

            if ($this->trustee === null) {
                throw new LogicException('The access control entry must have a trustee.');
            }

            $body = pack('V', $this->rights);

            if ($this->isObjectAce()) {
                $body .= pack('V', $this->objectFlags)
                    .($this->objectType?->getBinary() ?? '')
                    .($this->inheritedObjectType?->getBinary() ?? '');
            }

            $body .= $this->trustee->getBinary().$this->applicationData;
        }

        $size = 4 + strlen($body);

        if ($size > 0xFFFF || $size % 4 !== 0) {
            throw new InvalidArgumentException('The access control entry size is invalid.');
        }

        return pack('CCv', $this->type, $this->flags, $size).$body;
    }

    /**
     * Set one of the optional object GUIDs while retaining unknown flag bits.
     */
    protected function setObjectGuid(string $property, Guid|string|null $guid, int $flag): static
    {
        $this->assertEditable();
        $guid = is_string($guid) ? new Guid($guid) : $guid;

        if ($guid !== null && ! $this->isObjectAce()) {
            $this->type = match ($this->type) {
                static::ACCESS_ALLOWED => static::ACCESS_ALLOWED_OBJECT,
                static::ACCESS_DENIED => static::ACCESS_DENIED_OBJECT,
                static::SYSTEM_AUDIT => static::SYSTEM_AUDIT_OBJECT,
            };
        }

        $this->{$property} = $guid;
        $this->objectFlags = $guid === null
            ? $this->objectFlags & ~$flag
            : $this->objectFlags | $flag;

        return $this;
    }

    /**
     * Prevent interpreting or rewriting an unknown entry layout.
     */
    protected function assertEditable(): void
    {
        if (! $this->isSupported()) {
            throw new LogicException('This access control entry can be preserved but its layout is unsupported.');
        }
    }
}
