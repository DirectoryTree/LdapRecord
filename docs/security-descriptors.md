# Active Directory security descriptors

Active Directory stores an object's owner, primary group, and permissions in the
binary `ntSecurityDescriptor` attribute. A discretionary access control list
(DACL) contains entries that allow or deny access. A system access control list
(SACL) contains auditing entries.

Use `withSecurityDescriptor()` to load the owner, group, and DACL:

```php
use LdapRecord\Models\ActiveDirectory\User;

$user = User::query()->withSecurityDescriptor()->findOrFail($dn);
$descriptor = $user->securityDescriptor();
```

`securityDescriptor()` returns a fresh instance of the loaded descriptor, or
`null` when the attribute was not returned. Changing that instance does not
change the model until you assign it or save it explicitly.

## Editing permissions

The DACL exposes an Illuminate collection of `Ace` instances through `getAces()`.
Use `addAce()` and `removeAce()` to change the list, or edit its entries directly:

```php
use LdapRecord\Models\Attributes\SecurityDescriptor\Ace;
use LdapRecord\Models\Attributes\Sid;

$descriptor->getDacl()->addAce(
    Ace::deny(Sid::SELF, Ace::CONTROL_ACCESS)->setObjectType(Ace::CHANGE_PASSWORD)
);

$descriptor->getDacl()->canonicalize();

$user->saveSecurityDescriptor($descriptor);
```

`toBinary()` preserves entry order. Calling `canonicalize()` explicitly places
explicit denies before explicit allows, followed by inherited entries in their
existing order. It requires a list of supported allow and deny entries; it throws
if it cannot safely classify an entry.

An absent ACL, a present null ACL, and an empty ACL remain distinct. In particular,
`setDacl(null)` sets a null DACL, which grants unrestricted access. `setDacl(new Acl)`
sets an empty DACL, which grants no access. `unsetDacl()` omits the DACL section.

Unsupported entry types, including callback entries, retain their binary payloads.
Their layouts cannot be edited through the typed entry setters. Existing control
bits, application data, audit entries, and optional object GUIDs are retained.

## Saving selected sections

`saveSecurityDescriptor()` immediately updates the DACL by default. It preserves
the other loaded descriptor sections and other pending model attributes. The
entry must already exist. The method fires the usual saving, updating, updated,
and saved events, and restores the connection's previous controls after the write.

Pass a combination of section constants to read or write other sections:

```php
use LdapRecord\Models\Attributes\SecurityDescriptor;

$parts = SecurityDescriptor::OWNER_SECURITY_INFORMATION
    | SecurityDescriptor::GROUP_SECURITY_INFORMATION
    | SecurityDescriptor::DACL_SECURITY_INFORMATION
    | SecurityDescriptor::SACL_SECURITY_INFORMATION;

$user = User::query()->withSecurityDescriptor($parts)->findOrFail($dn);
$descriptor = $user->securityDescriptor();

// Make the required edits before saving.
$user->saveSecurityDescriptor($descriptor, $parts);
```

Reading and writing each section requires the corresponding Active Directory
permissions. SACL access usually requires additional privileges. LDAP errors
propagate through LdapRecord's existing exception handling.

You can also assign a descriptor to `$user->ntSecurityDescriptor`; the model stores
its binary value. Ordinary `save()` retains its existing behavior and does not
choose descriptor sections. Use `saveSecurityDescriptor()` for a scoped update.
Serialization represents this attribute as base64 and restores its binary value
when unserializing the model.

## Disabling password changes

The runnable [password example](../examples/disable-password-changes.php) denies
the change-password right for Everyone and Self, preserving other entries. It
updates existing matching explicit entries so repeated runs do not add duplicates.
It requires an existing DACL with supported entries for canonicalization.

Set `LDAP_HOST`, `LDAP_BASE_DN`, `LDAP_USERNAME`, and `LDAP_PASSWORD`, then run:

```sh
php examples/disable-password-changes.php 'CN=Jane Doe,OU=Users,DC=example,DC=com'
```

The example uses StartTLS and requires a trusted server certificate. Password
change restrictions use permissions rather than the `PASSWD_CANT_CHANGE` account
control flag. They do not prevent an administrator with the reset-password right
from resetting a password.
