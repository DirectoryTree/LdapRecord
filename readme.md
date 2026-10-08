<p align="center">
    <img src="https://ldaprecord.com/logo.svg" width="300" alt="LdapRecord">
</p>

<p align="center">An Active Record ORM for working with LDAP directories.</p>

<p align="center">
    <a href="https://github.com/DirectoryTree/LdapRecord/actions/workflows/run-tests.yml"><img src="https://img.shields.io/github/actions/workflow/status/DirectoryTree/LdapRecord/run-tests.yml?branch=master&amp;style=flat-square" alt="Tests"></a>
    <a href="https://packagist.org/packages/directorytree/ldaprecord"><img src="https://img.shields.io/packagist/dt/directorytree/ldaprecord.svg?style=flat-square" alt="Total Downloads"></a>
    <a href="https://packagist.org/packages/directorytree/ldaprecord"><img src="https://img.shields.io/packagist/v/directorytree/ldaprecord.svg?style=flat-square" alt="Latest Version"></a>
    <a href="https://github.com/DirectoryTree/LdapRecord/blob/master/license.md"><img src="https://img.shields.io/github/license/DirectoryTree/LdapRecord?style=flat-square" alt="License"></a>
</p>

<p align="center">
    <a href="#installation">Installation</a>
    <span> · </span>
    <a href="https://ldaprecord.com/docs/core/v4/">Documentation</a>
    <span> · </span>
    <a href="https://github.com/DirectoryTree/LdapRecord-Laravel">Laravel Integration</a>
    <span> · </span>
    <a href="https://github.com/DirectoryTree/LdapRecord/discussions/new">Post a Question</a>
</p>

---

## Installation

Install the package via Composer:

```bash
composer require directorytree/ldaprecord
```

See the [installation guide](https://ldaprecord.com/docs/core/v4/installation/) for requirements and setup, then follow the [quickstart](https://ldaprecord.com/docs/core/v4/quickstart/) to connect to your directory.

## Features

### Up and Running Fast

Connect to your LDAP servers and start running queries in a matter of minutes.

### Fluent Filter Builder

Find the LDAP objects you're looking for with a fluent LDAP filter builder.

### Multi-Domain Ready

Built-in connection management allows you to access multiple domains without breaking a sweat.

### Supercharged Active Record

Create and modify LDAP objects with minimal code.

## Active Directory Features

### Enable / Disable Accounts

Detect and assign User Account Control values on accounts with the fluent [Account Control builder](https://ldaprecord.com/docs/core/v4/active-directory/users/#uac).

### Reset / Change Passwords

Built-in support for [changing](https://ldaprecord.com/docs/core/v4/active-directory/users/#changing-passwords) and [resetting](https://ldaprecord.com/docs/core/v4/active-directory/users/#resetting-passwords) passwords on Active Directory accounts.

### Restore Deleted Objects

Seamlessly access your Active Directory recycle bin and [restore deleted objects](https://ldaprecord.com/docs/core/v4/models/#restoring-deleted-models).

## LdapRecord is Supportware™

If you require support using LdapRecord, a [sponsorship](https://github.com/sponsors/stevebauman) is required :pray:

Thank you for your understanding :heart:

## Security Vulnerabilities

If you discover a security vulnerability within LdapRecord, please send an e-mail to Steve Bauman via [steven_bauman@outlook.com](mailto:steven_bauman@outlook.com).

All security vulnerabilities will be promptly addressed.

## Credits

This package is directly inspired from [Laravel's Eloquent](https://laravel.com/docs/eloquent), and most features are direct ports to an LDAP equivalent.

I am forever grateful for the work [Taylor Otwell](https://github.com/taylorotwell) has produced.

If you can, support his work by purchasing a [sponsorship](https://github.com/sponsors/taylorotwell), or one of his many Laravel based services.
