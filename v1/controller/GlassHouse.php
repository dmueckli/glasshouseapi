<?php

require_once('db.php');
require_once('../model/Response.php');
require_once('../model/GlassHouse.php');
require_once('../model/Host.php');

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

//Begin of the authorization script

// Check for authorization header 
if (!isset($_SERVER['HTTP_AUTHORIZATION']) || strlen($_SERVER['HTTP_AUTHORIZATION']) < 1) {
    $response = new Response(false, 401);
    (!isset($_SERVER['HTTP_AUTHORIZATION']) ? $response->addMessage('Access token is missing from the header.') : false);
    (strlen($_SERVER['HTTP_AUTHORIZATION']) < 1 ? $response->addMessage('Access token cannot be blank.') : false);
    $response->send();
    exit();
}

try { // Try to get user credentials from db using the given accesstoken
    $accessToken = $_SERVER['HTTP_AUTHORIZATION'];

    $query = $writeDB->prepare('SELECT userid, accesstokenexpiry, active, login_attempts, type, hostid FROM tbl_sessions, tbl_users WHERE tbl_sessions.userid = tbl_users.id AND accesstoken = :accesstoken');
    $query->bindParam(':accesstoken', $accessToken, PDO::PARAM_STR);
    $query->execute();

    $rowCount = $query->rowCount();

    if ($rowCount === 0) { // Error response if theres no session with this access token
        $response = new Response(false, 401, 'Invalid access token.');
        $response->send();
        exit();
    }

    $row = $query->fetch(PDO::FETCH_ASSOC); // Get user credentials from database 

    $returned_userid = $row['userid'];
    $returned_accesstokenexpiry = $row['accesstokenexpiry'];
    $returned_useractive = $row['active'];
    $returned_loginattempts = $row['login_attempts'];
    $returned_type = $row['type'];
    $returned_hostid = $row['hostid'];

    if ($returned_useractive !== 'Y') {
        $response = new Response(false, 401, 'User account is not active.');
        $response->send();
        exit();
    }

    if ($returned_loginattempts >= 3) {
        $response = new Response(false, 401, 'User account is currently locked out.');
        $response->send();
        exit();
    }

    if (strtotime($returned_accesstokenexpiry) < time()) {
        $response = new Response(false, 401, 'Access token has been expired.');
        $response->send();
        exit();
    }
} catch (PDOException $ex) {
    error_log('Database Query Error: ' . $ex, 0);
    $response = new Response(false, 500, 'There was an issue authenticating - please try again.');
    $response->send();
    exit();
} // End of the authorization script

if (array_key_exists('id', $_GET)) {
    $sensorDataId = $_GET['id'];

    if ($sensorDataId == '' || !is_numeric($sensorDataId)) {
        $response = new Response(false, 400, 'WeatherData ID cannot be blank or must be numeric.', null, false);
        $response->send();
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {

        try {
            //code...
            // $query = $readDB->prepare('SELECT id, name, humidity, soil_moisture, temperature, heat_index, time FROM 	tbl_sensordata, tbl_hosts WHERE 	tbl_sensordata.id = :id AND tbl_hosts = host_id');
            $query = $readDB->prepare('SELECT tbl_sensordata.id, tbl_hosts.id AS hostid, tbl_hosts.name as hostname, tbl_versions.name as version, tbl_hosts.mac, INET_NTOA(tbl_hosts.local_ip) AS local_ip, INET_NTOA(tbl_hosts.gateway_ip) AS gateway_ip, tbl_sensordata.humidity, tbl_sensordata.soil_moisture, tbl_sensordata.temperature, tbl_sensordata.heat_index, tbl_sensordata.time FROM tbl_sensordata INNER JOIN tbl_hosts ON tbl_sensordata.host_id = tbl_hosts.id INNER JOIN tbl_versions ON tbl_hosts.versionid = tbl_versions.id WHERE tbl_sensordata.id = :id');
            $query->bindParam(':id', $sensorDataId, PDO::PARAM_INT);
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

                $host = new Host($row['hostid'], $row['hostname'], $row['version'], $row['mac'], $row['local_ip'], $row['gateway_ip']);

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
        $response = new Response(false, 501, 'Request method is not implemented!', null, false);
        $response->send();
        exit;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
        $response = new Response(false, 501, 'Request method is not implemented!', null, false);
        $response->send();
        exit;
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
            if (!isset($jsonData['sensor_data']['humidity']) || !isset($jsonData['sensor_data']['soil_moisture']) || !isset($jsonData['sensor_data']['temperature']) || !isset($jsonData['sensor_data']['heat_index'])) {

                $response = new Response(false, 400, null, null, false);

                (!isset($jsonData['sensor_data']['humidity']) ? $response->addMessage('Humidity field is mandatory and must be provided.') : false);

                (!isset($jsonData['sensor_data']['soil_moisture']) ? $response->addMessage('Soil moisture field is mandatory and must be provided.') : false);

                (!isset($jsonData['sensor_data']['temperature']) ? $response->addMessage('Temperature field is mandatory and must be provided.') : false);

                (!isset($jsonData['sensor_data']['heat_index']) ? $response->addMessage('Heat index field is mandatory and must be provided.') : false);

                $response->send();
                exit;
            }

            // create new task with data, if non mandatory fields not provided then set to null
            // $weatherdata = array();

            $weather = new WeatherData(null, $jsonData['sensor_data']['humidity'], $jsonData['sensor_data']['soil_moisture'], $jsonData['sensor_data']['temperature'], $jsonData['sensor_data']['heat_index'], null);

            $humidity = $weather->getHumidity();
            $soilMoisture = $weather->getSoilMoisture();
            $tempCelsius = $weather->getTemperature();
            $heatIndex = $weather->getHeatIndex();

            $query = $writeDB->prepare('INSERT INTO tbl_sensordata (id, host_id, user_id, humidity, soil_moisture, temperature, heat_index, time) VALUES (NULL, :hostId, :userId, :humidity, :soil_moisture, :temperature, :heatIndex, now())');

            $query->bindParam(':hostId', $returned_hostid, PDO::PARAM_INT);
            $query->bindParam(':userId', $returned_userid, PDO::PARAM_INT);
            $query->bindParam(':humidity', $humidity, PDO::PARAM_STR);
            $query->bindParam(':soil_moisture', $soilMoisture, PDO::PARAM_STR);
            $query->bindParam(':temperature', $tempCelsius, PDO::PARAM_STR);
            $query->bindParam(':heatIndex', $heatIndex, PDO::PARAM_STR);
            $query->execute();

            // get row count
            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                // set up response for unsuccessful return
                $response = new Response(false, 500, "Failed to create sensor data.", null, false);
                $response->send();
                exit;
            }

            // get last task id so we can return the Task in the json
            $lastId = $writeDB->lastInsertId();

            $query = $readDB->prepare('SELECT tbl_sensordata.id, tbl_hosts.id AS hostid, tbl_hosts.name as hostname, tbl_versions.name as version, tbl_hosts.mac, INET_NTOA(tbl_hosts.local_ip) AS local_ip, INET_NTOA(tbl_hosts.gateway_ip) AS gateway_ip, tbl_sensordata.humidity, tbl_sensordata.soil_moisture, tbl_sensordata.temperature, tbl_sensordata.heat_index, tbl_sensordata.time FROM tbl_sensordata INNER JOIN tbl_hosts ON tbl_sensordata.host_id = tbl_hosts.id INNER JOIN tbl_versions ON tbl_hosts.versionid = tbl_versions.id WHERE tbl_sensordata.id = :id');
            $query->bindParam(':id', $lastId, PDO::PARAM_INT);
            $query->execute();

            // get row count
            $rowCount = $query->rowCount();

            // make sure that the new task was returned
            if ($rowCount === 0) {
                // set up response for unsuccessful return
                $response = new Response(false, 500, "Failed to retrieve sensor data from database after creation.", null, false);
                $response->send();
                exit;
            }

            $weatherdata = array();

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                # code...

                $weather = new WeatherData($row['id'], $row['humidity'], $row['soil_moisture'], $row['temperature'], $row['heat_index'], $row['time']);

                $host = new Host($row['hostid'], $row['hostname'], $row['version'], $row['gateway_ip'], $row['local_ip'], $row['mac']);

                $hostarray = $host->returnAsArray();
                $sensorData = $weather->returnAsArray();

                $weatherDataArray[] = $sensorData;
            }

            // bundle tasks and rows returned into an array to return in the json data
            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            // $returnData['host'] = $hostarray;
            $returnData['sensor_data'] = $weatherDataArray;

            //set up response for successful return
            $response = new Response(true, 200, 'Query OK! Data created. sensorDataId: ' . $lastId, $returnData, true);
            $response->send();
            exit;
        } catch (WeatherDataException $wx) {
            //throw $th;
            $response = new Response(false, 400, $wx->getMessage(), null, false);
            $response->send();
            exit();
        } catch (PDOException $ex) {
            //throw $th;
            $response = new Response(false, 500, 'Failed to insert sensordata into database - please check the submitted data.', null, false);
            $response->send();
            exit();
        }
    } else {
        $response = new Response(false, 405, 'Request method is not allowed!', null, false);
        $response->send();
        exit;
    }
} else {
    # code...
    $response = new Response(false, 404, 'Endpoint not found!', null, false);
    $response->send();
    exit;
}
