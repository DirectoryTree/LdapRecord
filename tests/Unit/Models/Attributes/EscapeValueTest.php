<?php

namespace LdapRecord\Tests\Unit\Models\Attributes;

use LdapRecord\Query\EscapedValue;
use LdapRecord\Tests\TestCase;

class EscapeValueTest extends TestCase
{
    protected array $escapedDnCharacters = ['\\', ',', '=', '+', '<', '>', ';', '"', '#'];

    protected array $escapedFilterCharacters = ['\\', '*', '(', ')', "\x00"];

    public function test_all_characters_are_escaped_by_default()
    {
        $characters = 'abcdefghijklmnopqrstuvwxyz';

        $this->assertEquals(
            '\61\62\63\64\65\66\67\68\69\6a\6b\6c\6d\6e\6f\70\71\72\73\74\75\76\77\78\79\7a',
            (new EscapedValue($characters))->get()
        );
    }

    public function test_reserved_dn_characters_are_escaped()
    {
        foreach ($this->escapedDnCharacters as $character) {
            $value = (new EscapedValue($character))->forDn()->get();

            $this->assertEquals(ldap_escape($character, '', LDAP_ESCAPE_DN), $value);
        }
    }

    public function test_reserved_filter_characters_are_escaped()
    {
        foreach ($this->escapedFilterCharacters as $character) {
            $value = (new EscapedValue($character))->forFilter()->get();

            $this->assertEquals(ldap_escape($character, '', LDAP_ESCAPE_FILTER), $value);
        }
    }

    public function test_both_dn_and_filter_reserved_characters_are_escaped()
    {
        $characters = array_merge($this->escapedFilterCharacters, $this->escapedDnCharacters);

        foreach ($characters as $character) {
            $value = (new EscapedValue($character))->forDnAndFilter()->get();

            $this->assertEquals(ldap_escape($character, '', LDAP_ESCAPE_FILTER + LDAP_ESCAPE_DN), $value);
        }
    }

    public function test_characters_can_be_ignored()
    {
        $this->assertEquals('\61', (new EscapedValue('a'))->get());
        $this->assertEquals('a\62', (new EscapedValue('ab'))->ignore('a')->get());
    }
}
