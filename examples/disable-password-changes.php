<?php

use LdapRecord\Connection;
use LdapRecord\Container;
use LdapRecord\Models\ActiveDirectory\User;
use LdapRecord\Models\Attributes\SecurityDescriptor\Ace;
use LdapRecord\Models\Attributes\Sid;

require __DIR__.'/../vendor/autoload.php';

Container::addConnection(new Connection([
    'hosts' => [getenv('LDAP_HOST')],
    'base_dn' => getenv('LDAP_BASE_DN'),
    'username' => getenv('LDAP_USERNAME'),
    'password' => getenv('LDAP_PASSWORD'),
    'use_tls' => true,
]));

$dn = $argv[1] ?? throw new InvalidArgumentException('Provide the user distinguished name as the first argument.');

$user = User::query()->withSecurityDescriptor()->findOrFail($dn);
$descriptor = $user->securityDescriptor() ?? throw new RuntimeException('The server did not return a security descriptor.');
$dacl = $descriptor->getDacl() ?? throw new RuntimeException('This example requires an existing DACL to preserve the other permissions.');

foreach ([Sid::EVERYONE, Sid::SELF] as $trustee) {
    $entry = $dacl->getAces()->first(fn (Ace $ace) => $ace->isSupported()
        && ($ace->isAllowAce() || $ace->isDenyAce())
        && ! $ace->isInherited()
        && (string) $ace->getTrustee() === $trustee
        && (string) $ace->getObjectType() === Ace::CHANGE_PASSWORD
        && $ace->getInheritedObjectType() === null
        && $ace->getRights() === Ace::CONTROL_ACCESS);

    if ($entry) {
        $entry->setType(Ace::ACCESS_DENIED_OBJECT);
    } else {
        $dacl->addAce(
            Ace::deny($trustee, Ace::CONTROL_ACCESS)->setObjectType(Ace::CHANGE_PASSWORD)
        );
    }
}

$dacl->canonicalize();

$user->saveSecurityDescriptor($descriptor);
