<?php

namespace LdapRecord\Models\Attributes\SecurityDescriptor;

use Illuminate\Support\Collection;
use InvalidArgumentException;
use LogicException;

/**
 * An access control list in a Windows security descriptor.
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
class Acl
{
    public const REVISION = 2;

    public const REVISION_DS = 4;

    protected int $revision = self::REVISION;

    protected int $reserved1 = 0;

    protected int $reserved2 = 0;

    protected string $padding = '';

    /** @var Collection<int, Ace> */
    protected Collection $aces;

    /**
     * Constructor.
     */
    public function __construct(?string $binary = null)
    {
        $this->aces = new Collection;

        if ($binary !== null) {
            $this->decode($binary);
        }
    }

    /**
     * Get the ACL revision.
     */
    public function getRevision(): int
    {
        return $this->aces->contains(fn (Ace $ace) => $ace->isObjectAce())
            ? static::REVISION_DS
            : $this->revision;
    }

    /**
     * Get the entries, in their stored order.
     *
     * @return Collection<int, Ace>
     */
    public function getAces(): Collection
    {
        return $this->aces;
    }

    /**
     * Add entries to the end of the list.
     */
    public function addAce(Ace ...$aces): static
    {
        $this->aces->push(...$aces);

        return $this;
    }

    /**
     * Remove the specified entry instances.
     */
    public function removeAce(Ace ...$aces): static
    {
        $this->aces = $this->aces->reject(fn (Ace $ace) => in_array($ace, $aces, true))->values();

        return $this;
    }

    /**
     * Order explicit denies before explicit allows, retaining inherited order.
     */
    public function canonicalize(): static
    {
        if ($this->aces->contains(fn (Ace $ace) => ! $ace->isAllowAce() && ! $ace->isDenyAce())) {
            throw new LogicException('Only allow and deny entries can be ordered canonically.');
        }

        $explicit = $this->aces->reject(fn (Ace $ace) => $ace->isInherited());

        $this->aces = $explicit->filter(fn (Ace $ace) => $ace->isDenyAce())
            ->concat($explicit->filter(fn (Ace $ace) => $ace->isAllowAce()))
            ->concat($this->aces->filter(fn (Ace $ace) => $ace->isInherited()))
            ->values();

        return $this;
    }

    /**
     * Encode the list without reordering entries.
     */
    public function toBinary(): string
    {
        $entries = $this->aces->map(fn (Ace $ace) => $ace->toBinary())->implode('');
        $size = 8 + strlen($entries) + strlen($this->padding);

        if ($size > 0xFFFF || $this->aces->count() > 0xFFFF) {
            throw new InvalidArgumentException('The access control list is too large.');
        }

        // Object-specific entries require the directory service ACL revision.
        return pack('CCvvv', $this->getRevision(), $this->reserved1, $size, $this->aces->count(), $this->reserved2)
            .$entries.$this->padding;
    }

    /**
     * Decode entries within the ACL's declared bounds.
     */
    protected function decode(string $binary): void
    {
        if (strlen($binary) < 8) {
            throw new InvalidArgumentException('The access control list header is incomplete.');
        }

        $header = unpack('Crevision/Creserved1/vsize/vcount/vreserved2', $binary);

        if (! in_array($header['revision'], [static::REVISION, static::REVISION_DS], true) || $header['size'] !== strlen($binary) || $header['size'] % 4 !== 0) {
            throw new InvalidArgumentException('The access control list header is invalid.');
        }

        $this->revision = $header['revision'];
        $this->reserved1 = $header['reserved1'];
        $this->reserved2 = $header['reserved2'];
        $offset = 8;

        for ($i = 0; $i < $header['count']; $i++) {
            if ($offset + 4 > strlen($binary)) {
                throw new InvalidArgumentException('The access control list entry count exceeds its size.');
            }

            $length = unpack('vsize', $binary, $offset + 2)['size'];

            if ($length < 4 || $offset + $length > strlen($binary)) {
                throw new InvalidArgumentException('An access control entry exceeds the list bounds.');
            }

            $this->aces->push(Ace::fromBinary(substr($binary, $offset, $length)));
            $offset += $length;
        }

        $this->padding = substr($binary, $offset);
    }
}
