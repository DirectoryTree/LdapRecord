# Binary fixtures

`self-owned.hex` and `active-directory.hex` come from Chad Sikorra's MIT-licensed
[LdapTools SecurityDescriptorSpec](https://github.com/ldaptools/ldaptools/blob/master/spec/LdapTools/Security/SecurityDescriptorSpec.php).
The Active Directory sample has 85 DACL entries and 3 SACL entries, with its owner
and group following the ACLs in the original binary layout.

`active-directory-normalized.hex` was produced independently with Python's
`struct` module. It copies each component using its original declared length and
rebuilds the header for owner, group, SACL, and DACL order. It does not reorder or
rewrite entries.

`object-deny.hex` is a hand-assembled object ACE with both GUIDs, an unknown object
flag, an unsigned high-bit access mask, and application data. The GUIDs use the
existing Guid test value and the reset-password extended right.

The upstream copyright and MIT notice are retained in
`third-party-licenses/ldaptools.txt` at the repository root.
