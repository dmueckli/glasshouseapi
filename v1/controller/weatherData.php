<?php

require_once('db.php');
require_once('../model/response.php');
require_once('../model/weatherData.php');
require_once('../model/host.php');

try {
    //code...
    $writeDB = DB::connectWriteDB();
    $readDB = DB::connectReadDB();
} catch (PDOException $ex) {
    //throw $th;
    error_log('Connection error - ' . $ex, 0);
    $response = new Response(false, 500, 'Database connection error!', null, false);
    $response->send();
    exit();
}

if (array_key_exists('weatherDataId', $_GET)) {
    $weatherDataId = $_GET['weatherDataId'];

    if ($weatherDataId == '' || !is_numeric($weatherDataId)) {
        $response = new Response(false, 400, 'WeatherData ID cannot be blank or must be numeric.', null, false);
        $response->send();
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {

        try {
            //code...
            // $query = $readDB->prepare('SELECT id, name, humidity, soil_moisture, temperature, heat_index, time FROM tbl_weatherdata, tbl_hosts WHERE tbl_weatherdata.id = :id AND tbl_hosts = host_id');
            $query = $readDB->prepare('SELECT tbl_weatherdata.id, tbl_hosts.id AS hostid, tbl_hosts.name, tbl_hosts.version, tbl_hosts.mac, INET_NTOA(tbl_hosts.local_ip) AS local_ip, INET_NTOA(tbl_hosts.gateway_ip) AS gateway_ip, tbl_weatherdata.humidity, tbl_weatherdata.soil_moisture, tbl_weatherdata.temperature, tbl_weatherdata.heat_index, tbl_weatherdata.time FROM tbl_weatherdata INNER JOIN tbl_hosts ON tbl_weatherdata.host_id = tbl_hosts.id WHERE tbl_weatherdata.id = :id');
            $query->bindParam(':id', $weatherDataId, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                # code...
                $response = new Response(false, 404, "Item not found!", null, false);
                $response->send();
                exit;
            }

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                # code...
                $weatherdata = array();

                $weather = new WeatherData($row['id'], $row['humidity'], $row['soil_moisture'], $row['temperature'], $row['heat_index'], $row['time']);

                $host = new Host($row['hostid'], $row['name'], $row['version'], $row['mac'], $row['local_ip'], $row['gateway_ip']);

                $weatherData['host'] = $host->returnAsArray();
                $weatherData['weather'] = $weather->returnAsArray();

                $weatherDataArray[] = $weatherData;
            }

            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['weatherData'] = $weatherDataArray;

            $response = new Response(true, 200, 'Query OK! Found ' . $rowCount . ' item(s).', $returnData, true);
            $response->send();
            exit;
        } catch (WeatherDataException $ex) {
            //throw $th;
            $response = new Response(false, 500, $ex->getMessage(), null, false);
            $response->send();
            exit;
        } catch (PDOException $ex) {
            //throw $th;
            error_log('Database query error - ' . $ex, 0);
            $response = new Response(false, 500, 'Failed to get weather data!', null, false);
            $response->send();
            exit();
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'DELETE') {
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
    } else {
        $response = new Response(false, 405, 'Request method is not allowed!', null, false);
        $response->send();
        exit;
    }
} elseif (empty($_GET)) {
    // POST data to the Server
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            //code...
            if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
                // set up response for unsuccessful request
                $response = new Response(false, 400, 'Content Type header not set to JSON!', null, false);
                $response->send();
                exit;
            }

            // get POST request body as the POSTed data will be JSON format
            $rawPostData = file_get_contents('php://input');

            if (!$jsonData = json_decode($rawPostData, true)) {
                // set up response for unsuccessful request
                $response = new Response(false, 400, 'Request body is not valid JSON.!', null, false);
                $response->send();
                exit;
            }

            // check if post request contains mandatory fields
            if (!isset($jsonData['host']['id']) || !isset($jsonData['host']['name']) || !isset($jsonData['host']['version']) || !isset($jsonData['host']['local ip']) || !isset($jsonData['host']['gateway ip']) || !isset($jsonData['host']['mac']) || !isset($jsonData['sensor data']['humidity']) || !isset($jsonData['sensor data']['soil moisture']) || !isset($jsonData['sensor data']['temperature °C']) || !isset($jsonData['sensor data']['heat index °C'])) {

                $response = new Response(false, 400, null, null, false);

                /* WILL BE SET DURING AUTHORIZATION!!! */
                (!isset($jsonData['host']['id']) ? $response->addMessage('Host ID field is mandatory and must be provided.') : false);

                (!isset($jsonData['host']['name']) ? $response->addMessage('Host name field is mandatory and must be provided.') : false);

                (!isset($jsonData['host']['version']) ? $response->addMessage('Version field is mandatory and must be provided.') : false);

                (!isset($jsonData['host']['local ip']) ? $response->addMessage('Local IP field is mandatory and must be provided.') : false);

                (!isset($jsonData['host']['gateway ip']) ? $response->addMessage('Gateway IP field is mandatory and must be provided.') : false);

                (!isset($jsonData['host']['mac']) ? $response->addMessage('Mac Address field is mandatory and must be provided.') : false);

                /**/
                
                (!isset($jsonData['sensor data']['humidity']) ? $response->addMessage('Humidity  field is mandatory and must be provided.') : false);

                (!isset($jsonData['sensor data']['soil moisture']) ? $response->addMessage('Soil moisture field is mandatory and must be provided.') : false);

                (!isset($jsonData['sensor data']['temperature °C']) ? $response->addMessage('Temperature field is mandatory and must be provided.') : false);

                (!isset($jsonData['sensor data']['heat index °C']) ? $response->addMessage('Heat index field is mandatory and must be provided.') : false);

                $response->send();
                exit;
            }

            // create new array with data, if non mandatory fields not provided then set to null
            // $weatherdata = array();

            $host = new Host($jsonData['host']['id'], $jsonData['host']['name'], $jsonData['host']['version'], $jsonData['host']['mac'], $jsonData['host']['local ip'], $jsonData['host']['gateway ip']);

            $hostId = $host->getID();
            $hostname = $host->getHostname();
            $version = $host->getVersion();
            $mac = $host->getMac();
            $localIp = $host->getLocalIp();
            $gatewayIp = $host->getGatewayIp();

            $weather = new WeatherData(null, $jsonData['sensor data']['humidity'], $jsonData['sensor data']['soil moisture'], $jsonData['sensor data']['temperature °C'], $jsonData['sensor data']['heat index °C'], null);

            $humidity = $weather->getHumidity();
            $soilMoisture = $weather->getSoilMoisture();
            $tempCelsius = $weather->getTemperature();
            $heatIndex = $weather->getHeatIndex();


            // create db query
            // $query = $writeDB->prepare('INSERT INTO tbltasks (title, description, deadline, completed, userid) VALUES (:title, :description, STR_TO_DATE(:deadline, "%d/%m/%Y %H:%i"), :completed, :userid)');

            $query = $writeDB->prepare('INSERT INTO tbl_weatherdata (id, host_id, humidity, soil_moisture, temperature, heat_index, time) VALUES (NULL, :hostId, :humidity, :soil_moisture, :temperature, :heatIndex, now())');

            $query->bindParam(':hostId', $hostId, PDO::PARAM_INT);
            $query->bindParam(':humidity', $humidity, PDO::PARAM_STR);
            $query->bindParam(':soil_moisture', $soilMoisture, PDO::PARAM_INT);
            $query->bindParam(':temperature', $tempCelsius, PDO::PARAM_STR);
            $query->bindParam(':heatIndex', $heatIndex, PDO::PARAM_INT);
            $query->execute();

            // get row count
            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                // set up response for unsuccessful return
                $response = new Response(false, 500, "Failed to create task.", null, false);
                $response->send();
                exit;
            }

            // get last task id so we can return the Task in the json
            $lastId = $writeDB->lastInsertId();

            $query = $readDB->prepare('SELECT tbl_weatherdata.id, tbl_hosts.id AS hostid, tbl_hosts.name, tbl_hosts.version, tbl_hosts.mac, INET_NTOA(tbl_hosts.local_ip) AS local_ip, INET_NTOA(tbl_hosts.gateway_ip) AS gateway_ip, tbl_weatherdata.humidity, tbl_weatherdata.soil_moisture, tbl_weatherdata.temperature, tbl_weatherdata.heat_index, tbl_weatherdata.time FROM tbl_weatherdata INNER JOIN tbl_hosts ON tbl_weatherdata.host_id = tbl_hosts.id WHERE tbl_weatherdata.id = :id');
            $query->bindParam(':id', $lastId, PDO::PARAM_INT);
            $query->execute();

            // get row count
            $rowCount = $query->rowCount();

            // make sure that the new task was returned
            if ($rowCount === 0) {
                // set up response for unsuccessful return
                $response = new Response(false, 500, "Failed to retrieve task from database after creation.", null, false);
                $response->send();
                exit;
            }

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                # code...
                $weatherdata = array();

                $weather = new WeatherData($row['id'], $row['humidity'], $row['soil_moisture'], $row['temperature'], $row['heat_index'], $row['time']);

                $host = new Host($row['hostid'], $row['name'], $row['version'], $row['mac'], $row['local_ip'], $row['gateway_ip']);

                $weatherData['host'] = $host->returnAsArray();
                $weatherData['weather'] = $weather->returnAsArray();

                $weatherDataArray[] = $weatherData;
            }

            // bundle tasks and rows returned into an array to return in the json data
            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['weatherData'] = $weatherDataArray;

            //set up response for successful return
            $response = new Response(true, 200, 'Query OK! Data created. weatherDataId: ' . $lastId, $returnData, true);
            $response->send();
            exit;
        } catch (WeatherDataException $wx) {
            //throw $th;
            $response = new Response(false, 400, $wx->getMessage(), null, false);
            $response->send();
            exit();
        } catch (PDOException $ex) {
            //throw $th;
            $response = new Response(false, 500, 'Failed to insert task into database - please check the submitted data.', null, false);
            $response->send();
            exit();
        }
    }
} else {
    # code...
    $response = new Response(false, 404, 'Endpoint not found!', null, false);
    $response->send();
    exit;
}
