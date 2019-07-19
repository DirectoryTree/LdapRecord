<?php

namespace LdapRecord\Models\ActiveDirectory;

use LdapRecord\Models\Types\ActiveDirectory;

/**
 * Class Printer.
 *
 * Represents an LDAP printer.
 */
class Printer extends Entry
{
    /**
     * The object classes of the LDAP model.
     * 
     * @var array
     */
    public static $objectClasses = ['printqueue'];
    
    /**
     * Returns the printers name.
     *
     * @link https://msdn.microsoft.com/en-us/library/ms679385(v=vs.85).aspx
     *
     * @return string
     */
    public function getPrinterName()
    {
        return $this->getFirstAttribute('printername');
    }

    /**
     * Returns the printers share name.
     *
     * @link https://msdn.microsoft.com/en-us/library/ms679408(v=vs.85).aspx
     *
     * @return string
     */
    public function getPrinterShareName()
    {
        return $this->getFirstAttribute('printsharename');
    }

    /**
     * Returns the printers memory.
     *
     * @link https://msdn.microsoft.com/en-us/library/ms679396(v=vs.85).aspx
     *
     * @return string
     */
    public function getMemory()
    {
        return $this->getFirstAttribute('printmemory');
    }

    /**
     * Returns the printers URL.
     *
     * @return string
     */
    public function getUrl()
    {
        return $this->getFirstAttribute('url');
    }

    /**
     * Returns the printers location.
     *
     * @link https://msdn.microsoft.com/en-us/library/ms676839(v=vs.85).aspx
     *
     * @return string
     */
    public function getLocation()
    {
        return $this->getFirstAttribute('location');
    }

    /**
     * Returns the server name that the
     * current printer is connected to.
     *
     * @link https://msdn.microsoft.com/en-us/library/ms679772(v=vs.85).aspx
     *
     * @return string
     */
    public function getServerName()
    {
        return $this->getFirstAttribute('servername');
    }

    /**
     * Returns true / false if the printer can print in color.
     *
     * @link https://msdn.microsoft.com/en-us/library/ms679382(v=vs.85).aspx
     *
     * @return null|bool
     */
    public function getColorSupported()
    {
        return $this->convertStringToBool(
            $this->getFirstAttribute('printcolor')
        );
    }

    /**
     * Returns true / false if the printer supports duplex printing.
     *
     * @link https://msdn.microsoft.com/en-us/library/ms679383(v=vs.85).aspx
     *
     * @return null|bool
     */
    public function getDuplexSupported()
    {
        return $this->convertStringToBool(
            $this->getFirstAttribute('printduplexsupported')
        );
    }

    /**
     * Returns an array of printer paper types that the printer supports.
     *
     * @link https://msdn.microsoft.com/en-us/library/ms679395(v=vs.85).aspx
     *
     * @return array
     */
    public function getMediaSupported()
    {
        return $this->getAttribute('printmediasupported');
    }

    /**
     * Returns true / false if the printer supports stapling.
     *
     * @link https://msdn.microsoft.com/en-us/library/ms679410(v=vs.85).aspx
     *
     * @return null|bool
     */
    public function getStaplingSupported()
    {
        return $this->convertStringToBool(
            $this->getFirstAttribute('printstaplingsupported')
        );
    }

    /**
     * Returns an array of the printers bin names.
     *
     * @link https://msdn.microsoft.com/en-us/library/ms679380(v=vs.85).aspx
     *
     * @return array
     */
    public function getPrintBinNames()
    {
        return $this->getAttribute('printbinnames');
    }

    /**
     * Returns the printers maximum resolution.
     *
     * @link https://msdn.microsoft.com/en-us/library/ms679391(v=vs.85).aspx
     *
     * @return string
     */
    public function getPrintMaxResolution()
    {
        return $this->getFirstAttribute('printmaxresolutionsupported');
    }

    /**
     * Returns the printers orientations supported.
     *
     * @link https://msdn.microsoft.com/en-us/library/ms679402(v=vs.85).aspx
     *
     * @return string
     */
    public function getPrintOrientations()
    {
        return $this->getFirstAttribute('printorientationssupported');
    }

    /**
     * Returns the driver name of the printer.
     *
     * @link https://msdn.microsoft.com/en-us/library/ms675652(v=vs.85).aspx
     *
     * @return string
     */
    public function getDriverName()
    {
        return $this->getFirstAttribute('drivername');
    }

    /**
     * Returns the printer drivers version number.
     *
     * @link https://msdn.microsoft.com/en-us/library/ms675653(v=vs.85).aspx
     *
     * @return string
     */
    public function getDriverVersion()
    {
        return $this->getFirstAttribute('driverversion');
    }

    /**
     * Returns the priority number of the printer.
     *
     * @link https://msdn.microsoft.com/en-us/library/ms679413(v=vs.85).aspx
     *
     * @return string
     */
    public function getPriority()
    {
        return $this->getFirstAttribute('priority');
    }

    /**
     * Returns the printers start time.
     *
     * @link https://msdn.microsoft.com/en-us/library/ms679411(v=vs.85).aspx
     *
     * @return string
     */
    public function getPrintStartTime()
    {
        return $this->getFirstAttribute('printstarttime');
    }

    /**
     * Returns the printers end time.
     *
     * @link https://msdn.microsoft.com/en-us/library/ms679384(v=vs.85).aspx
     *
     * @return string
     */
    public function getPrintEndTime()
    {
        return $this->getFirstAttribute('printendtime');
    }

    /**
     * Returns the port name of printer.
     *
     * @link https://msdn.microsoft.com/en-us/library/ms679131(v=vs.85).aspx
     *
     * @return string
     */
    public function getPortName()
    {
        return $this->getFirstAttribute('portname');
    }

    /**
     * Returns the printers version number.
     *
     * @link https://msdn.microsoft.com/en-us/library/ms680897(v=vs.85).aspx
     *
     * @return string
     */
    public function getVersionNumber()
    {
        return $this->getFirstAttribute('versionnumber');
    }

    /**
     * Returns the print rate.
     *
     * @link https://msdn.microsoft.com/en-us/library/ms679405(v=vs.85).aspx
     *
     * @return string
     */
    public function getPrintRate()
    {
        return $this->getFirstAttribute('printrate');
    }

    /**
     * Returns the print rate unit.
     *
     * @link https://msdn.microsoft.com/en-us/library/ms679406(v=vs.85).aspx
     *
     * @return string
     */
    public function getPrintRateUnit()
    {
        return $this->getFirstAttribute('printrateunit');
    }
}
