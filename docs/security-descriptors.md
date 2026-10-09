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
change the model until you assign it back to the attribute.

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

$user->ntSecurityDescriptor = $descriptor;
$user->save();
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

## Saving changes

Assign the edited descriptor to `ntSecurityDescriptor` and call `save()`. The model
compares its owner, group, DACL, SACL, and section control flags with the original
descriptor. It automatically applies the Active Directory control for the
sections that changed. Editing only the DACL does not request an owner, group,
or SACL update.

Resource manager header edits are available through the binary utilities;
`save()` accepts changes to the four sections and their associated flags.

Other pending attributes are saved in the same LDAP modification request:

```php
$user->ntSecurityDescriptor = $descriptor;
$user->description = 'Updated permissions';
$user->save();
```

Saving uses the usual model events and change tracking. The connection's previous
controls are restored after the write. If the write fails, the original attribute
values remain unchanged and your edits remain pending for a retry. `saveQuietly()`
also supports descriptor changes.

Edit the loaded descriptor to retain its other sections and permissions. To edit
the owner, group, or SACL, include the required sections when reading:

```php
use LdapRecord\Models\Attributes\SecurityDescriptor;

$parts = SecurityDescriptor::OWNER_SECURITY_INFORMATION
    | SecurityDescriptor::GROUP_SECURITY_INFORMATION
    | SecurityDescriptor::DACL_SECURITY_INFORMATION
    | SecurityDescriptor::SACL_SECURITY_INFORMATION;

$user = User::query()->withSecurityDescriptor($parts)->findOrFail($dn);
$descriptor = $user->securityDescriptor();

$descriptor->setOwner('S-1-5-21-100-200-300-500');

$user->ntSecurityDescriptor = $descriptor;
$user->save();
```

Reading and writing each section requires the corresponding Active Directory
permissions. SACL access usually requires additional privileges. LDAP errors
propagate through LdapRecord's existing exception handling.

The attribute accepts a `SecurityDescriptor`, a raw binary string, or the existing
LDAP attribute array format. Storage remains binary internally. Serialization
represents this attribute as base64 and restores its binary value when
unserializing the model.

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
