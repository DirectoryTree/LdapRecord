<?php

namespace LdapRecord;

use LDAP\Connection as RawLdapConnection;

/** @psalm-suppress UndefinedClass */
class Ldap implements LdapInterface
{
    use HandlesConnection, DetectsErrors;

    /**
     * @inheritdoc
     */
    public function getEntries($result)
    {
        return $this->executeFailableOperation(
            fn () => ldap_get_entries($this->connection, $result)
        );
    }

    /**
     * Retrieves the first entry from a search result.
     *
     * @see http://php.net/manual/en/function.ldap-first-entry.php
     *
     * @param \Ldap\Result $result
     *
     * @return resource
     */
    public function getFirstEntry($result)
    {
        return $this->executeFailableOperation(
            fn () => ldap_first_entry($this->connection, $result)
        );
    }

    /**
     * Retrieves the next entry from a search result.
     *
     * @see http://php.net/manual/en/function.ldap-next-entry.php
     *
     * @param \Ldap\ResultEntry $entry
     *
     * @return resource
     */
    public function getNextEntry($entry)
    {
        return $this->executeFailableOperation(
            fn () => ldap_next_entry($this->connection, $entry)
        );
    }

    /**
     * Retrieves the ldap entry's attributes.
     *
     * @see http://php.net/manual/en/function.ldap-get-attributes.php
     *
     * @param \Ldap\ResultEntry $entry
     *
     * @return array|false
     */
    public function getAttributes($entry)
    {
        return $this->executeFailableOperation(
            fn () => ldap_get_attributes($this->connection, $entry)
        );
    }

    /**
     * Returns the number of entries from a search result.
     *
     * @see http://php.net/manual/en/function.ldap-count-entries.php
     *
     * @param \Ldap\Result $result
     *
     * @return int
     */
    public function countEntries($result)
    {
        return $this->executeFailableOperation(
            fn () => ldap_count_entries($this->connection, $result)
        );
    }

    /**
     * Compare value of attribute found in entry specified with DN.
     *
     * @see http://php.net/manual/en/function.ldap-compare.php
     *
     * @param string $dn
     * @param string $attribute
     * @param string $value
     *
     * @return mixed
     */
    public function compare($dn, $attribute, $value)
    {
        return $this->executeFailableOperation(
            fn () => ldap_compare($this->connection, $dn, $attribute, $value)
        );
    }

    /**
     * @inheritdoc
     */
    public function getLastError()
    {
        if (! $this->connection) {
            return;
        }

        return ldap_error($this->connection);
    }

    /**
     * @inheritdoc
     */
    public function getDetailedError()
    {
        if (! $number = $this->errNo()) {
            return;
        }

        $this->getOption(LDAP_OPT_DIAGNOSTIC_MESSAGE, $message);

        return new DetailedError($number, $this->err2Str($number), $message);
    }

    /**
     * Get all binary values from the specified result entry.
     *
     * @see http://php.net/manual/en/function.ldap-get-values-len.php
     *
     * @param $entry
     * @param $attribute
     *
     * @return array
     */
    public function getValuesLen($entry, $attribute)
    {
        return $this->executeFailableOperation(
            fn () => ldap_get_values_len($this->connection, $entry, $attribute)
        );
    }

    /**
     * @inheritdoc
     */
    public function setOption($option, $value)
    {
        return ldap_set_option($this->connection, $option, $value);
    }

    /**
     * @inheritdoc
     */
    public function getOption($option, &$value = null)
    {
        ldap_get_option($this->connection, $option, $value);

        return $value;
    }

    /**
     * Set a callback function to do re-binds on referral chasing.
     *
     * @see http://php.net/manual/en/function.ldap-set-rebind-proc.php
     *
     * @param callable $callback
     *
     * @return bool
     */
    public function setRebindCallback(callable $callback)
    {
        return ldap_set_rebind_proc($this->connection, $callback);
    }

    /**
     * @inheritdoc
     */
    public function startTLS()
    {
        return $this->executeFailableOperation(
            fn () => ldap_start_tls($this->connection)
        );
    }

    /**
     * @inheritdoc
     */
    public function connect($hosts = [], $port = 389)
    {
        $this->bound = false;

        $this->host = $this->makeConnectionUris($hosts, $port);

        return $this->connection = $this->executeFailableOperation(
            fn () => ldap_connect($this->host)
        );
    }

    /**
     * @inheritdoc
     */
    public function close()
    {
        $result = false;

        if ($this->connection instanceof RawLdapConnection) {
            $result = @ldap_close($this->connection);
        }

        $this->connection = null;
        $this->bound = false;
        $this->host = null;

        return $result;
    }

    /**
     * @inheritdoc
     */
    public function search($dn, $filter, array $fields, $onlyAttributes = false, $size = 0, $time = 0, $deref = LDAP_DEREF_NEVER, array $controls = null)
    {
        return $this->executeFailableOperation(
            fn () => ldap_search($this->connection, $dn, $filter, $fields, $onlyAttributes, $size, $time, $deref, $controls)
        );
    }

    /**
     * @inheritdoc
     */
    public function list($dn, $filter, array $fields, $onlyAttributes = false, $size = 0, $time = 0, $deref = LDAP_DEREF_NEVER, array $controls = null)
    {
        return $this->executeFailableOperation(
            fn () => ldap_list($this->connection, $dn, $filter, $fields, $onlyAttributes, $size, $time, $deref, $controls)
        );
    }

    /**
     * @inheritdoc
     */
    public function read($dn, $filter, array $fields, $onlyAttributes = false, $size = 0, $time = 0, $deref = LDAP_DEREF_NEVER, array $controls = null)
    {
        return $this->executeFailableOperation(
            fn () => ldap_read($this->connection, $dn, $filter, $fields, $onlyAttributes, $size, $time, $deref, $controls)
        );
    }

    /**
     * @inheritdoc
     */
    public function parseResult($result, &$errorCode, &$dn, &$errorMessage, &$referrals, array &$controls = null)
    {
        $success = ldap_parse_result($this->connection, $result, $errorCode, $dn, $errorMessage, $referrals, $controls);
        
        if ($success) {
            return new LdapResultResponse(
                $errorCode, $dn, $errorMessage, $referrals, $controls
            );
        }

        return $success;
    }

    /**
     * @inheritdoc
     */
    public function bind($username = null, $password = null, $controls = null)
    {
        $result = $this->executeFailableOperation(
            fn () => ldap_bind_ext($this->connection, $username, $password ? html_entity_decode($password) : null, $controls)
        );

        $response = $this->parseResult($result, $errorCode, $dn, $errorMessage, $refs);

        $this->bound = $response && $response->successful();

        return $response;
    }

    /**
     * @inheritdoc
     */
    public function add($dn, array $entry)
    {
        return $this->executeFailableOperation(
            fn () => ldap_add($this->connection, $dn, $entry)
        );
    }

    /**
     * @inheritdoc
     */
    public function delete($dn)
    {
        return $this->executeFailableOperation(
            fn () => ldap_delete($this->connection, $dn)
        );
    }

    /**
     * @inheritdoc
     */
    public function rename($dn, $newRdn, $newParent, $deleteOldRdn = false)
    {
        return $this->executeFailableOperation(
            fn () => ldap_rename($this->connection, $dn, $newRdn, $newParent, $deleteOldRdn)
        );
    }

    /**
     * @inheritdoc
     */
    public function modify($dn, array $entry)
    {
        return $this->executeFailableOperation(
            fn () => ldap_modify($this->connection, $dn, $entry)
        );
    }

    /**
     * @inheritdoc
     */
    public function modifyBatch($dn, array $values)
    {
        return $this->executeFailableOperation(
            fn () => ldap_modify_batch($this->connection, $dn, $values)
        );
    }

    /**
     * @inheritdoc
     */
    public function modAdd($dn, array $entry)
    {
        return $this->executeFailableOperation(
            fn () => ldap_mod_add($this->connection, $dn, $entry)
        );
    }

    /**
     * @inheritdoc
     */
    public function modReplace($dn, array $entry)
    {
        return $this->executeFailableOperation(
            fn () => ldap_mod_replace($this->connection, $dn, $entry)
        );
    }

    /**
     * @inheritdoc
     */
    public function modDelete($dn, array $entry)
    {
        return $this->executeFailableOperation(
            fn () => ldap_mod_del($this->connection, $dn, $entry)
        );
    }

    /**
     * @inheritdoc
     */
    public function freeResult($result)
    {
        return ldap_free_result($result);
    }

    /**
     * @inheritdoc
     */
    public function errNo()
    {
        return $this->connection ? ldap_errno($this->connection) : null;
    }

    /**
     * @inheritdoc
     */
    public function err2Str($number)
    {
        return ldap_err2str($number);
    }

    /**
     * Returns the extended error hex code of the last command.
     *
     * @return string|null
     */
    public function getExtendedErrorHex()
    {
        if (preg_match("/(?<=data\s).*?(?=,)/", $this->getExtendedError(), $code)) {
            return $code[0];
        }
    }

    /**
     * Returns the extended error code of the last command.
     *
     * @return bool|string
     */
    public function getExtendedErrorCode()
    {
        return $this->extractDiagnosticCode($this->getExtendedError());
    }

    /**
     * Extract the diagnostic code from the message.
     *
     * @param string $message
     *
     * @return string|bool
     */
    public function extractDiagnosticCode($message)
    {
        preg_match('/^([\da-fA-F]+):/', $message, $matches);

        return isset($matches[1]) ? $matches[1] : false;
    }

    /**
     * @inheritdoc
     */
    public function getDiagnosticMessage()
    {
        $this->getOption(LDAP_OPT_ERROR_STRING, $message);

        return $message;
    }
}
