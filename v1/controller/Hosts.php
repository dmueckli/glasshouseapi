<?php

require_once('db.php');
require_once('../model/Response.php');
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

if (array_key_exists('hostId', $_GET)) {
    $hostId = $_GET['hostId'];

    if ($hostId == '' || !is_numeric($hostId)) {
        $response = new Response(false, 400, 'Host ID cannot be blank or must be numeric.', null, false);
        $response->send();
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'GET') {

        try {
            //code...
            // $query = $readDB->prepare('SELECT id, name, humidity, soil_moisture, temperature, heat_index, time FROM tbl_host, tbl_hosts WHERE tbl_host.id = :id AND tbl_hosts = host_id');
            $query = $readDB->prepare('SELECT tbl_hosts.id AS hostid, tbl_hosts.name, tbl_hosts.version, tbl_hosts.mac, INET_NTOA(tbl_hosts.local_ip) AS local_ip, INET_NTOA(tbl_hosts.gateway_ip) AS gateway_ip FROM tbl_hosts WHERE tbl_hosts.id = :id');
            $query->bindParam(':id', $hostId, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                # code...
                $response = new Response(false, 404, "Item not found!", null, false);
                $response->send();
                exit;
            }

            $row = $query->fetch(PDO::FETCH_ASSOC);

            $host = new Host($row['hostid'], $row['name'], $row['version'], $row['gateway_ip'], $row['local_ip'], $row['mac']);

            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['host'] = $host->returnAsArray();

            $response = new Response(true, 200, 'Query OK! Found ' . $rowCount . ' item(s).', $returnData, true);
            $response->send();
            exit;
        } catch (HostException $ex) {
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
        $response = new Response(false, 405, 'Request method is not implemented!', null, false);
        $response->send();
        exit;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
        $response = new Response(false, 405, 'Request method is not implemented!', null, false);
        $response->send();
        exit;
    } else {
        $response = new Response(false, 405, 'Request method is not allowed!', null, false);
        $response->send();
        exit;
    }
} elseif (empty($_GET)) {

    // $response = new Response(false, 405, 'Request method is not allowed!', null, false);
    // $response->send();
    // exit;
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
            if (!isset($jsonData['host']['name']) || !isset($jsonData['host']['version']) || !isset($jsonData['host']['local ip']) || !isset($jsonData['host']['gateway ip']) || !isset($jsonData['host']['mac'])) {

                $response = new Response(false, 400, null, null, false);

                // (!isset($jsonData['host']['id']) ? $response->addMessage('Host ID field is mandatory and must be provided.') : false);

                (!isset($jsonData['host']['name']) ? $response->addMessage('Host name field is mandatory and must be provided.') : false);

                (!isset($jsonData['host']['version']) ? $response->addMessage('Version field is mandatory and must be provided.') : false);

                (!isset($jsonData['host']['local ip']) ? $response->addMessage('Local IP field is mandatory and must be provided.') : false);

                (!isset($jsonData['host']['gateway ip']) ? $response->addMessage('Gateway IP field is mandatory and must be provided.') : false);

                (!isset($jsonData['host']['mac']) ? $response->addMessage('Mac Address field is mandatory and must be provided.') : false);

                /**/

                $response->send();
                exit;
            }

            // create new array with data, if non mandatory fields not provided then set to null
            // $host = array();

            $host = new Host(null, $jsonData['host']['name'], $jsonData['host']['version'], $jsonData['host']['gateway ip'], $jsonData['host']['local ip'], $jsonData['host']['mac']);

            // $hostId = $host->getID();
            $hostname = $host->getHostname();
            $version = $host->getVersion();
            $gatewayIp = $host->getGatewayIp();
            $localIp = $host->getLocalIp();
            $mac = $host->getMac();

            // create db query
            // $query = $writeDB->prepare('INSERT INTO tbltasks (title, description, deadline, completed, userid) VALUES (:title, :description, STR_TO_DATE(:deadline, "%d/%m/%Y %H:%i"), :completed, :userid)');

            // $query = $writeDB->prepare('INSERT INTO tbl_hosts (id, name, version, mac, INET_ATON(tbl_hosts.local_ip) AS local_ip, INET_ATON(tbl_hosts.gateway_ip) AS gateway_ip) VALUES (NULL, :name, :version, :mac, :localip, :gatewayip)');

            $query = $writeDB->prepare('INSERT INTO tbl_hosts (id, name, version, mac, local_ip, gateway_ip) VALUES (NULL, :name, :version, :mac, INET_ATON(:localip), INET_ATON(:gatewayip))');

            // $query->bindParam(':hostId', $hostId, PDO::PARAM_INT);
            $query->bindParam(':name', $hostname, PDO::PARAM_STR);
            $query->bindParam(':version', $version, PDO::PARAM_INT);
            $query->bindParam(':gatewayip', $gatewayIp, PDO::PARAM_STR);
            $query->bindParam(':localip', $localIp, PDO::PARAM_STR);
            $query->bindParam(':mac', $mac, PDO::PARAM_STR);
            $query->execute();

            // get row count
            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                // set up response for unsuccessful return
                $response = new Response(false, 500, "Failed to create host!", null, false);
                $response->send();
                exit;
            }

            // get last task id so we can return the Task in the json
            $lastId = $writeDB->lastInsertId();

            $query = $readDB->prepare('SELECT tbl_hosts.id AS hostid, tbl_hosts.name, tbl_hosts.version, tbl_hosts.mac, INET_NTOA(tbl_hosts.local_ip) AS local_ip, INET_NTOA(tbl_hosts.gateway_ip) AS gateway_ip FROM tbl_hosts WHERE tbl_hosts.id = :id');
            $query->bindParam(':id', $lastId, PDO::PARAM_INT);
            $query->execute();

            // get row count
            $rowCount = $query->rowCount();

            // make sure that the new task was returned
            if ($rowCount === 0) {
                // set up response for unsuccessful return
                $response = new Response(false, 500, "Failed to retrieve host from database after creation.", null, false);
                $response->send();
                exit;
            }

            $row = $query->fetch(PDO::FETCH_ASSOC);

            $host = new Host($row['hostid'], $row['name'], $row['version'], $row['gateway_ip'], $row['local_ip'], $row['mac']);

            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['host'] = $host->returnAsArray();

            $response = new Response(true, 200, 'Query OK! Found ' . $rowCount . ' item(s).', $returnData, true);
            $response->send();
            exit;
        } catch (hostException $wx) {
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
