<?php

class HostException extends Exception
{
}

class Host
{

    private $_id;
    private $_hostname;
    private $_version;
    private $_mac;
    private $_localip;
    private $_gatewayip;

    public function __construct($id, $hostname, $version, $gatewayip, $localip, $mac)
    {
        $this->setID($id);
        $this->setHostname($hostname);
        $this->setVersion($version);
        $this->setGatewayIp($gatewayip);
        $this->setLocalIp($localip);
        $this->setMac($mac);
        
    }

    public function getID()
    {
        return $this->_id;
    }

    public function getHostname()
    {
        return $this->_hostname;
    }

    public function getVersion()
    {
        return $this->_version;
    }

    public function getGatewayIp()
    {
        return $this->_gatewayip;
    }

    public function getLocalIp()
    {
        return $this->_localip;
    }

    public function getMac()
    {
        return $this->_mac;
    }

    // Setters
    public function setID($id)
    {
        if (($id !== null) && (!is_numeric($id) || $id <= 0 || $id > 9223372036854775807 || $this->_id !== null)) {
            throw new HostException('Error setting Host ID.');
        }

        $this->_id = $id;
    }

    public function setHostname($hostname)
    {
        if (strlen($hostname) < 0 || strlen($hostname) > 255) {
            throw new HostException('Error setting Hostname.');
        }

        $this->_hostname = $hostname;
    }

    public function setVersion($version)
    {
        if (strlen($version) < 0 || strlen($version) > 255) {
            throw new HostException('Error setting Host version.');
        }

        $this->_version = $version;
    }

    public function setGatewayIp($ip)
    {
        // if (($ip !== null) && (!is_numeric($ip) || $ip <= 0 || $ip > 9223372036854775807 || $this->_gatewayip !== null)) {
        //     throw new HostException('Error setting Hosts local IP.');
        // }

        $this->_gatewayip = $ip;
    }

    public function setLocalIp($ip)
    {
        // if (($ip !== null) && (!is_numeric($ip) || $ip <= 0 || $ip > 9223372036854775807 || $this->_localip !== null)) {
        //     throw new HostException('Error setting Hosts local IP.');
        // }

        $this->_localip = $ip;
    }

    public function setMac($mac)
    {
        if (strlen($mac) < 0 || strlen($mac) > 17) {
            throw new HostException('Error setting Host mac address.');
        }

        $this->_mac = $mac;
    }

    public function returnAsArray()
    {
        $weatherData = array();
        $weatherData['id'] = $this->getID();
        $weatherData['hostname'] = $this->getHostname();
        $weatherData['version'] = $this->getVersion();
        $weatherData['gateway ip'] = $this->getGatewayIp();
        $weatherData['local ip'] = $this->getLocalIp();
        $weatherData['mac'] = $this->getMac();
        return $weatherData;
    }
}
