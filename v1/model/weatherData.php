<?php

class WeatherDataException extends Exception
{
}

class WeatherData
{

    private $_id;
    private $_hostId;
    private $_hostname;
    private $_humidity;
    private $_soilMoisture;
    private $_temperature;
    private $_heatIndex;
    private $_time;

    public function __construct($id, /*$hostId,*/  /*$hostname,*/ $humidity, $soilMoisture, $temperature, $heatIndex, $time)
    {
        $this->setID($id);
        /*$this->setHostId($hostId);*/
        // $this->setHostname($hostname);
        $this->setHumidity($humidity);
        $this->setSoilMoisture($soilMoisture);
        $this->setTemperature($temperature);
        $this->setHeatIndex($heatIndex);
        $this->setTime($time);
    }

    public function getID()
    {
        return $this->_id;
    }

    // public function getHostId()
    // {
    //     return $this->_hostId;
    // }

    // public function getHostname()
    // {
    //     return $this->_hostname;
    // }

    public function getHumidity()
    {
        return $this->_humidity;
    }

    public function getSoilMoisture()
    {
        return $this->_soilMoisture;
    }

    public function getTemperature()
    {
        return $this->_temperature;
    }

    public function getHeatIndex()
    {
        return $this->_heatIndex;
    }

    public function getTime()
    {
        return $this->_time;
    }

    // Setters
    public function setID($id)
    {
        if (($id !== null) && (!is_numeric($id) || $id <= 0 || $id > 9223372036854775807 || $this->_id !== null)) {
            throw new WeatherDataException('Error setting WeatherData ID.');
        }

        $this->_id = $id;
    }

    // public function setHostId($hostId)
    // {
    //     if (($hostId !== null) && (!is_numeric($hostId) || $hostId <= 0 || $hostId > 9223372036854775807 || $this->_hostId !== null)) {
    //         throw new WeatherDataException('Error setting Host ID.');
    //     }

    //     $this->_hostId = $hostId;
    // }

    // public function setHostname($hostname)
    // {
    //     if (strlen($hostname) < 0 || strlen($hostname) > 255) {
    //         throw new WeatherDataException('Task Title error.');
    //     }

    //     $this->_hostname = $hostname;
    // }

    public function setHumidity($humidity)
    {
        if (($humidity !== null) && (!is_numeric($humidity)) && (strlen($humidity) > 15)) {
            throw new WeatherDataException('Error setting humidity.');
        }

        $this->_humidity = $humidity;
    }

    public function setSoilMoisture($soilMoisture)
    {
        if (($soilMoisture !== null) && (!is_numeric($soilMoisture)) && (strlen($soilMoisture) > 15)) {
            throw new WeatherDataException('Error setting soil moisture.');
        }

        $this->_soilMoisture = $soilMoisture;
    }

    public function setTemperature($temperature)
    {
        if (($temperature !== null) && (!is_numeric($temperature)) && (strlen($temperature) > 15)) {
            throw new WeatherDataException('Error setting temperature.');
        }

        $this->_temperature = $temperature;
    }

    public function setHeatIndex($heatIndex)
    {
        if (($heatIndex !== null) && (!is_numeric($heatIndex)) && (strlen($heatIndex) > 15)) {
            throw new WeatherDataException('Error setting heat index.');
        }

        $this->_heatIndex = $heatIndex;
    }

    public function setTime($time)
    {
        $this->_time = $time;
    }

    public function returnAsArray()
    {
        $weatherData = array();
        $weatherData['id'] = $this->getID();
        //$weatherData['hostname'] = $this->getHostname();
        $weatherData['humidity'] = $this->getHumidity();
        $weatherData['soil moisture'] = $this->getSoilMoisture();
        $weatherData['temperature'] = $this->getTemperature();
        $weatherData['heat index'] = $this->getHeatIndex();
        $weatherData['time'] = $this->getTime();
        return $weatherData;
    }
}
