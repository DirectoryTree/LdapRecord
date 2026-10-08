<?php

namespace LdapRecord\Models\Attributes;

use InvalidArgumentException;
use LdapRecord\Models\Attributes\SecurityDescriptor\Acl;

/**
 * The self-relative Windows security descriptor stored in ntSecurityDescriptor.
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
class SecurityDescriptor
{
    public const OWNER_SECURITY_INFORMATION = 0x01;

    public const GROUP_SECURITY_INFORMATION = 0x02;

    public const DACL_SECURITY_INFORMATION = 0x04;

    public const SACL_SECURITY_INFORMATION = 0x08;

    public const OWNER_DEFAULTED = 0x0001;

    public const GROUP_DEFAULTED = 0x0002;

    public const DACL_PRESENT = 0x0004;

    public const DACL_DEFAULTED = 0x0008;

    public const SACL_PRESENT = 0x0010;

    public const SACL_DEFAULTED = 0x0020;

    public const DACL_UNTRUSTED = 0x0040;

    public const SERVER_SECURITY = 0x0080;

    public const DACL_AUTO_INHERIT_REQUESTED = 0x0100;

    public const SACL_AUTO_INHERIT_REQUESTED = 0x0200;

    public const DACL_AUTO_INHERITED = 0x0400;

    public const SACL_AUTO_INHERITED = 0x0800;

    public const DACL_PROTECTED = 0x1000;

    public const SACL_PROTECTED = 0x2000;

    public const RM_CONTROL_VALID = 0x4000;

    public const SELF_RELATIVE = 0x8000;

    protected int $revision = 1;

    protected int $resourceManagerControl = 0;

    protected int $controlFlags = self::SELF_RELATIVE;

    protected ?Sid $owner = null;

    protected ?Sid $group = null;

    protected ?Acl $dacl = null;

    protected ?Acl $sacl = null;

    /**
     * Constructor.
     */
    public function __construct(?string $binary = null)
    {
        if ($binary !== null) {
            $this->decode($binary);
        }
    }

    /**
     * Get the descriptor revision.
     */
    public function getRevision(): int
    {
        return $this->revision;
    }

    /**
     * Get the resource manager control byte.
     */
    public function getResourceManagerControl(): int
    {
        return $this->resourceManagerControl;
    }

    /**
     * Set the resource manager control byte.
     */
    public function setResourceManagerControl(int $control): static
    {
        if ($control < 0 || $control > 0xFF) {
            throw new InvalidArgumentException('The resource manager control must fit in one byte.');
        }

        $this->resourceManagerControl = $control;

        return $this;
    }

    /**
     * Get the descriptor control flags.
     */
    public function getControlFlags(): int
    {
        return $this->controlFlags;
    }

    /**
     * Set the flags for a self-relative descriptor.
     */
    public function setControlFlags(int $flags): static
    {
        if ($flags < 0 || $flags > 0xFFFF || ! ($flags & static::SELF_RELATIVE)) {
            throw new InvalidArgumentException('The control flags must describe a self-relative security descriptor.');
        }

        $this->controlFlags = $flags;

        return $this;
    }

    /**
     * Get the owner SID.
     */
    public function getOwner(): ?Sid
    {
        return $this->owner;
    }

    /**
     * Set the owner SID.
     */
    public function setOwner(Sid|string|null $owner): static
    {
        $this->owner = is_string($owner) ? new Sid($owner) : $owner;

        return $this;
    }

    /**
     * Get the primary group SID.
     */
    public function getGroup(): ?Sid
    {
        return $this->group;
    }

    /**
     * Set the primary group SID.
     */
    public function setGroup(Sid|string|null $group): static
    {
        $this->group = is_string($group) ? new Sid($group) : $group;

        return $this;
    }

    /**
     * Get the discretionary access control list.
     */
    public function getDacl(): ?Acl
    {
        return $this->dacl;
    }

    /**
     * Determine whether the DACL is present, including a present null DACL.
     */
    public function hasDacl(): bool
    {
        return (bool) ($this->controlFlags & static::DACL_PRESENT);
    }

    /**
     * Set the DACL. A null DACL grants unrestricted access.
     */
    public function setDacl(?Acl $dacl): static
    {
        $this->dacl = $dacl;
        $this->controlFlags |= static::DACL_PRESENT;

        return $this;
    }

    /**
     * Omit the DACL from the descriptor.
     */
    public function unsetDacl(): static
    {
        $this->dacl = null;
        $this->controlFlags &= ~static::DACL_PRESENT;

        return $this;
    }

    /**
     * Get the system access control list.
     */
    public function getSacl(): ?Acl
    {
        return $this->sacl;
    }

    /**
     * Determine whether the SACL is present, including a present null SACL.
     */
    public function hasSacl(): bool
    {
        return (bool) ($this->controlFlags & static::SACL_PRESENT);
    }

    /**
     * Set the system access control list.
     */
    public function setSacl(?Acl $sacl): static
    {
        $this->sacl = $sacl;
        $this->controlFlags |= static::SACL_PRESENT;

        return $this;
    }

    /**
     * Omit the SACL from the descriptor.
     */
    public function unsetSacl(): static
    {
        $this->sacl = null;
        $this->controlFlags &= ~static::SACL_PRESENT;

        return $this;
    }

    /**
     * Merge selected descriptor sections, retaining the other sections and flags.
     */
    public function merge(SecurityDescriptor $descriptor, int $parts): static
    {
        $flags = 0;

        if ($parts & static::OWNER_SECURITY_INFORMATION) {
            $this->owner = $descriptor->owner;
            $flags |= static::OWNER_DEFAULTED;
        }

        if ($parts & static::GROUP_SECURITY_INFORMATION) {
            $this->group = $descriptor->group;
            $flags |= static::GROUP_DEFAULTED;
        }

        if ($parts & static::DACL_SECURITY_INFORMATION) {
            $this->dacl = $descriptor->dacl;
            $flags |= static::DACL_PRESENT | static::DACL_DEFAULTED | static::DACL_UNTRUSTED
                | static::SERVER_SECURITY | static::DACL_AUTO_INHERIT_REQUESTED
                | static::DACL_AUTO_INHERITED | static::DACL_PROTECTED;
        }

        if ($parts & static::SACL_SECURITY_INFORMATION) {
            $this->sacl = $descriptor->sacl;
            $flags |= static::SACL_PRESENT | static::SACL_DEFAULTED | static::SACL_AUTO_INHERIT_REQUESTED
                | static::SACL_AUTO_INHERITED | static::SACL_PROTECTED;
        }

        $this->controlFlags = ($this->controlFlags & ~$flags) | ($descriptor->controlFlags & $flags);

        return $this;
    }

    /**
     * Get the sections whose contents or control flags differ from the original.
     */
    public function getChangedParts(SecurityDescriptor $original): int
    {
        $parts = 0;
        $binary = $original->toBinary();

        foreach ([
            static::OWNER_SECURITY_INFORMATION,
            static::GROUP_SECURITY_INFORMATION,
            static::DACL_SECURITY_INFORMATION,
            static::SACL_SECURITY_INFORMATION,
        ] as $part) {
            if ((clone $original)->merge($this, $part)->toBinary() !== $binary) {
                $parts |= $part;
            }
        }

        return $parts;
    }

    /**
     * Encode the descriptor with byte offsets relative to its 20-byte header.
     */
    public function toBinary(): string
    {
        if (($this->dacl && ! $this->hasDacl()) || ($this->sacl && ! $this->hasSacl())) {
            throw new InvalidArgumentException('A populated access control list must have its present flag set.');
        }

        $body = '';
        $offsets = [];

        foreach ([$this->owner, $this->group, $this->sacl, $this->dacl] as $component) {
            $offsets[] = $component === null ? 0 : 20 + strlen($body);
            $body .= $component instanceof Sid ? $component->getBinary() : ($component?->toBinary() ?? '');
        }

        return pack('CCvV4', $this->revision, $this->resourceManagerControl, $this->controlFlags, ...$offsets).$body;
    }

    /**
     * Decode a self-relative descriptor within its component bounds.
     */
    protected function decode(string $binary): void
    {
        if (strlen($binary) < 20) {
            throw new InvalidArgumentException('The security descriptor header is incomplete.');
        }

        $header = unpack('Crevision/CresourceManagerControl/vcontrolFlags/Vowner/Vgroup/Vsacl/Vdacl', $binary);

        if ($header['revision'] !== 1) {
            throw new InvalidArgumentException('The security descriptor revision is unsupported.');
        }

        $this->setControlFlags($header['controlFlags']);
        $this->resourceManagerControl = $header['resourceManagerControl'];

        foreach (['owner', 'group', 'sacl', 'dacl'] as $property) {
            $offset = $header[$property];

            if ($offset === 0) {
                continue;
            }

            if ($offset < 20 || $offset % 4 !== 0 || $offset + 8 > strlen($binary)) {
                throw new InvalidArgumentException('A security descriptor component offset is invalid.');
            }

            $isSid = in_array($property, ['owner', 'group'], true);
            $length = $isSid
                ? 8 + ord($binary[$offset + 1]) * 4
                : unpack('vsize', $binary, $offset + 2)['size'];

            if ($length < 8 || $offset + $length > strlen($binary)) {
                throw new InvalidArgumentException('A security descriptor component exceeds its bounds.');
            }

            $component = substr($binary, $offset, $length);
            $this->{$property} = $isSid ? new Sid($component) : new Acl($component);
        }

        if (($this->dacl && ! $this->hasDacl()) || ($this->sacl && ! $this->hasSacl())) {
            throw new InvalidArgumentException('An access control list offset requires its present flag.');
        }
    }
}
