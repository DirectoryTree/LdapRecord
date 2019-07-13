<?php

namespace LdapRecord\Models\Concerns;

trait HasUserProperties
{
    /**
     * Returns the users country.
     *
     * @return string|null
     */
    public function getCountry()
    {
        return $this->getFirstAttribute('c');
    }

    /**
     * Sets the users country.
     *
     * @param string $country
     *
     * @return $this
     */
    public function setCountry($country)
    {
        return $this->setFirstAttribute('c', $country);
    }

    /**
     * Returns the users department.
     *
     * @link https://msdn.microsoft.com/en-us/library/ms675490(v=vs.85).aspx
     *
     * @return string|null
     */
    public function getDepartment()
    {
        return $this->getFirstAttribute('department');
    }

    /**
     * Sets the users department.
     *
     * @param string $department
     *
     * @return $this
     */
    public function setDepartment($department)
    {
        return $this->setFirstAttribute('department', $department);
    }

    /**
     * Returns the users email address.
     *
     * @link https://msdn.microsoft.com/en-us/library/ms676855(v=vs.85).aspx
     *
     * @return string|null
     */
    public function getEmail()
    {
        return $this->getFirstAttribute('mail');
    }

    /**
     * Sets the users email.
     *
     * Keep in mind this will remove all other
     * email addresses the user currently has.
     *
     * @param string $email
     *
     * @return $this
     */
    public function setEmail($email)
    {
        return $this->setFirstAttribute('mail', $email);
    }

    /**
     * Returns the users facsimile number.
     *
     * @link https://msdn.microsoft.com/en-us/library/ms675675(v=vs.85).aspx
     *
     * @return string|null
     */
    public function getFacsimileNumber()
    {
        return $this->getFirstAttribute('facsimiletelephonenumber');
    }

    /**
     * Sets the users facsimile number.
     *
     * @param string $number
     *
     * @return $this
     */
    public function setFacsimileNumber($number)
    {
        return $this->setFirstAttribute('facsimiletelephonenumber', $number);
    }

    /**
     * Returns the users first name.
     *
     * @link https://msdn.microsoft.com/en-us/library/ms675719(v=vs.85).aspx
     *
     * @return string|null
     */
    public function getFirstName()
    {
        return $this->getFirstAttribute('givenname');
    }

    /**
     * Sets the users first name.
     *
     * @param string $firstName
     *
     * @return $this
     */
    public function setFirstName($firstName)
    {
        return $this->setFirstAttribute('givenname', $firstName);
    }

    /**
     * Returns the users initials.
     *
     * @return string|null
     */
    public function getInitials()
    {
        return $this->getFirstAttribute('initials');
    }

    /**
     * Sets the users initials.
     *
     * @param string $initials
     *
     * @return $this
     */
    public function setInitials($initials)
    {
        return $this->setFirstAttribute('initials', $initials);
    }

    /**
     * Returns the users IP Phone.
     *
     * @return string|null
     */
    public function getIpPhone()
    {
        return $this->getFirstAttribute('ipphone');
    }

    /**
     * Sets the users IP phone.
     *
     * @param string $ip
     *
     * @return $this
     */
    public function setIpPhone($ip)
    {
        return $this->setFirstAttribute('ipphone', $ip);
    }

    /**
     * Returns the users last name.
     *
     * @link https://msdn.microsoft.com/en-us/library/ms679872(v=vs.85).aspx
     *
     * @return string|null
     */
    public function getLastName()
    {
        return $this->getFirstAttribute('sn');
    }

    /**
     * Sets the users last name.
     *
     * @param string $lastName
     *
     * @return $this
     */
    public function setLastName($lastName)
    {
        return $this->setFirstAttribute('sn', $lastName);
    }

    /**
     * Returns the users postal code.
     *
     * @return string|null
     */
    public function getPostalCode()
    {
        return $this->getFirstAttribute('postalcode');
    }

    /**
     * Sets the users postal code.
     *
     * @param string $postalCode
     *
     * @return $this
     */
    public function setPostalCode($postalCode)
    {
        return $this->setFirstAttribute('postalcode', $postalCode);
    }

    /**
     * Get the users post office box.
     *
     * @return string|null
     */
    public function getPostOfficeBox()
    {
        return $this->getFirstAttribute('postofficebox');
    }

    /**
     * Sets the users post office box.
     *
     * @param string|int $box
     *
     * @return $this
     */
    public function setPostOfficeBox($box)
    {
        return $this->setFirstAttribute('postofficebox', $box);
    }

    /**
     * Sets the users proxy addresses.
     *
     * This will remove all proxy addresses on the user and insert the specified addresses.
     *
     * @link https://msdn.microsoft.com/en-us/library/ms679424(v=vs.85).aspx
     *
     * @param array $addresses
     *
     * @return $this
     */
    public function setProxyAddresses(array $addresses = [])
    {
        return $this->setAttribute('proxyaddresses', $addresses);
    }

    /**
     * Add's a single proxy address to the user.
     *
     * @param string $address
     *
     * @return $this
     */
    public function addProxyAddress($address)
    {
        $addresses = $this->getProxyAddresses();

        $addresses[] = $address;

        return $this->setAttribute('proxyaddresses', $addresses);
    }

    /**
     * Returns the users proxy addresses.
     *
     * @link https://msdn.microsoft.com/en-us/library/ms679424(v=vs.85).aspx
     *
     * @return array
     */
    public function getProxyAddresses()
    {
        return $this->getAttribute('proxyaddresses') ?? [];
    }

    /**
     * Returns the users street address.
     *
     * @return string|null
     */
    public function getStreetAddress()
    {
        return $this->getFirstAttribute('streetaddress');
    }

    /**
     * Sets the users street address.
     *
     * @param string $address
     *
     * @return $this
     */
    public function setStreetAddress($address)
    {
        return $this->setFirstAttribute('streetaddress', $address);
    }

    /**
     * Returns the users title.
     *
     * @link https://msdn.microsoft.com/en-us/library/ms680037(v=vs.85).aspx
     *
     * @return string|null
     */
    public function getTitle()
    {
        return $this->getFirstAttribute('title');
    }

    /**
     * Sets the users title.
     *
     * @param string $title
     *
     * @return $this
     */
    public function setTitle($title)
    {
        return $this->setFirstAttribute('title', $title);
    }

    /**
     * Returns the users telephone number.
     *
     * @link https://msdn.microsoft.com/en-us/library/ms680027(v=vs.85).aspx
     *
     * @return string|null
     */
    public function getTelephoneNumber()
    {
        return $this->getFirstAttribute('telephonenumber');
    }

    /**
     * Sets the users telephone number.
     *
     * @param string $number
     *
     * @return $this
     */
    public function setTelephoneNumber($number)
    {
        return $this->setFirstAttribute('telephonenumber', $number);
    }

    /**
     * Returns the users primary mobile phone number.
     *
     * @return string|null
     */
    public function getMobileNumber()
    {
        return $this->getFirstAttribute('mobile');
    }

    /**
     * Sets the users primary mobile phone number.
     *
     * @param string $number
     *
     * @return $this
     */
    public function setMobileNumber($number)
    {
        return $this->setFirstAttribute('mobile', $number);
    }

    /**
     * Returns the users secondary (other) mobile phone number.
     *
     * @return string|null
     */
    public function getOtherMobileNumber()
    {
        return $this->getFirstAttribute('othermobile');
    }

    /**
     * Sets the users  secondary (other) mobile phone number.
     *
     * @param string $number
     *
     * @return $this
     */
    public function setOtherMobileNumber($number)
    {
        return $this->setFirstAttribute('othermobile', $number);
    }

    /**
     * Returns the users other mailbox attribute.
     *
     * @link https://msdn.microsoft.com/en-us/library/ms679091(v=vs.85).aspx
     *
     * @return array
     */
    public function getOtherMailbox()
    {
        return $this->getAttribute('othermailbox');
    }

    /**
     * Sets the users other mailboxes.
     *
     * @param array $otherMailbox
     *
     * @return $this
     */
    public function setOtherMailbox($otherMailbox = [])
    {
        return $this->setAttribute('othermailbox', $otherMailbox);
    }

    /**
     * Returns the distinguished name of the user who is the user's manager.
     *
     * @return string|null
     */
    public function getManager()
    {
        return $this->getFirstAttribute('manager');
    }

    /**
     * Sets the distinguished name of the user who is the user's manager.
     *
     * @param string $managerDn
     *
     * @return $this
     */
    public function setManager($managerDn)
    {
        return $this->setFirstAttribute('manager', $managerDn);
    }

    /**
     * Returns the users mail nickname.
     *
     * @return string|null
     */
    public function getMailNickname()
    {
        return $this->getFirstAttribute('mailnickname');
    }
}
