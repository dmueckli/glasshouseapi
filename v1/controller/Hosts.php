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
            $query = $readDB->prepare('SELECT tbl_hosts.id AS hostid, tbl_hosts.name, tbl_versions.name as version, tbl_hosts.mac, INET_NTOA(tbl_hosts.local_ip) AS local_ip, INET_NTOA(tbl_hosts.gateway_ip) AS gateway_ip FROM tbl_hosts INNER JOIN tbl_versions ON tbl_hosts.versionid = tbl_versions.id WHERE tbl_hosts.id = :id');
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
        $response = new Response(false, 501, 'Request method is not implemented!', null, false);
        $response->send();
        exit;
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PATCH') {
        // $response = new Response(false, 501, 'Request method is not implemented!', null, false);
        // $response->send();
        // exit;
        try {

            if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
                $response = new Response(false, 400, 'Content Type header not set to JSON.');
                $response->send();
                exit();
            }

            $rawPatchData = file_get_contents('php://input');

            if (!$jsonData = json_decode($rawPatchData)) {
                $response = new Response(false, 400, 'Request body is not valid JSON.');
                $response->send();
                exit();
            }

            // Checking for updated fields and set the query string accordingly.
            $hostname_updated = false;
            $version_updated = false;
            $gatewayip_updated = false;
            $localip_updated = false;
            $mac_updated = false;

            $queryFields = '';

            if (isset($jsonData->host->name)) {
                $hostname_updated = true;

                $queryFields .= 'name = :name, ';
            }

            if (isset($jsonData->host->version)) {
                // TODO: Implement check if the given version exists 
                // TODO: Implement function to insert new version if none exists
                // Check if the given version exists 
                // and insert new version if none
                $version = $jsonData->host->version;

                $query = $writeDB->prepare('SELECT id, name, description, active FROM tbl_versions WHERE name = :version');
                $query->bindParam(':version', $version, PDO::PARAM_STR);
                $query->execute();

                // get row count
                $rowCount = $query->rowCount();

                if ($rowCount === 0) {
                    $query = $writeDB->prepare('INSERT INTO tbl_versions (id, name) VALUES (NULL, :version)');
                    $query->bindParam(':version', $version, PDO::PARAM_STR);
                    $query->execute();

                    $lastVersionId = $writeDB->lastInsertId();

                    $query = $writeDB->prepare('SELECT id, name, description, active FROM tbl_versions WHERE id = :version');
                    $query->bindParam(':version', $lastVersionId, PDO::PARAM_STR);
                    $query->execute();

                    // get row count
                    $rowCount = $query->rowCount();
                }

                if ($rowCount === 0) {
                    # code...
                    $response = new Response(false, 500, 'Failed to create version!', null, false);
                    $response->send();
                    exit;
                }

                $row = $query->fetch(PDO::FETCH_ASSOC); // Get version details from database 

                $returned_versionId = $row['id'];
                $returned_versionName = $row['name'];
                $returned_versionDescription = $row['description'];
                $returned_versionActive = $row['active'];
                // END OF VERSION CHECK SCRIPTS

                $version_updated = true;
                $queryFields .= 'versionid = :versionid, ';
            }

            if (isset($jsonData->host->gateway_ip)) {
                $gatewayip_updated = true;
                $queryFields .= 'gateway_ip = INET_ATON(:gateway_ip), ';
            }

            if (isset($jsonData->host->local_ip)) {
                $localip_updated = true;
                $queryFields .= 'local_ip = INET_ATON(:local_ip), ';
            }

            if (isset($jsonData->host->mac)) {
                $mac_updated = true;
                $queryFields .= 'mac = :mac, ';
            }

            // This strips the last comma from the end of the queryFields string.
            $queryFields = rtrim($queryFields, ', ');

            if ($hostname_updated === false && $version_updated === false && $gatewayip_updated === false && $localip_updated === false && $mac_updated === false) {
                $response = new Response(false, 400, 'No fields provided to update.', null, false);
                $response->send();
                exit();
            }

            $query = $writeDB->prepare('SELECT id, name, versionid, INET_NTOA(gateway_ip) AS gateway_ip, INET_NTOA(local_ip) AS local_ip, mac FROM tbl_hosts WHERE id = :hostid');
            $query->bindParam(':hostid', $hostId, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                $response = new Response(false, 404, 'No host found to update.');
                $response->send();
                exit();
            }

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $host = new Host($row['id'], $row['name'], $row['version'], $row['gateway_ip'], $row['local_ip'], $row['mac']);
            }

            $queryString = 'UPDATE tbl_hosts SET ' . $queryFields . ' WHERE id = :hostid';

            $query = $writeDB->prepare($queryString);

            if ($hostname_updated === true) {
                $host->setHostname($jsonData->host->name);

                $up_hostname = $host->getHostname();

                $query->bindParam(':name', $up_hostname, PDO::PARAM_STR);
            }

            if ($version_updated === true) {
                $host->setVersion($returned_versionId);

                $up_version = $host->getVersion();

                $query->bindParam(':versionid', $up_version, PDO::PARAM_STR);
            }

            if ($gatewayip_updated === true) {
                $host->setGatewayIp($jsonData->host->gateway_ip);

                $up_gatewayip = $host->getGatewayIp();

                $query->bindParam(':gateway_ip', $up_gatewayip, PDO::PARAM_STR);
            }

            if ($localip_updated === true) {
                $host->setLocalIp($jsonData->host->local_ip);

                $up_localip = $host->getLocalIp();

                $query->bindParam(':local_ip', $up_localip, PDO::PARAM_STR);
            }

            if ($mac_updated === true) {
                $host->setMac($jsonData->host->mac);

                $up_mac = $host->getMac();

                $query->bindParam(':mac', $up_mac, PDO::PARAM_STR);
            }

            $query->bindParam(':hostid', $hostId, PDO::PARAM_INT);
            // $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                $response = new Response(false, 400, 'Host not updated - given values may be the same as the stored values.');
                $response->send();
                exit;
            }

            $query = $writeDB->prepare('SELECT tbl_hosts.id AS hostid, tbl_hosts.name, tbl_versions.name as version, INET_NTOA(tbl_hosts.gateway_ip) AS gateway_ip, INET_NTOA(tbl_hosts.local_ip) AS local_ip, tbl_hosts.mac FROM tbl_hosts INNER JOIN tbl_versions ON tbl_hosts.versionid = tbl_versions.id WHERE tbl_hosts.id = :hostid');
            $query->bindParam(':hostid', $hostId, PDO::PARAM_INT);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                $response = new Response(false, 404, 'No host found.');
                $response->send();
                exit;
            }

            $hostArray = array();

            while ($row = $query->fetch(PDO::FETCH_ASSOC)) {
                $host = new Host($row['hostid'], $row['name'], $row['version'], $row['gateway_ip'], $row['local_ip'], $row['mac']);

                $hostArray[] = $host->returnAsArray();
            }

            $returnData = array();
            $returnData['rows_returned'] = $rowCount;
            $returnData['hosts'] = $hostArray;

            $response = new Response(true, 200, 'Host updated.', $returnData);
            $response->send();
            exit();
        } catch (HostException $ex) {
            $response = new Response(false, 400, $ex->getMessage());
            $response->send();
            exit();
        } catch (PDOException $ex) {
            error_log('Database Query Error: ' . $ex, 0);
            $response = new Response(false, 500, 'Failed to update Host - check your data for errors.');
            $response->send();
            exit();
        }
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
                exit();
            }

            // get POST request body as the POSTed data will be JSON format
            $rawPostData = file_get_contents('php://input');

            if (!$jsonData = json_decode($rawPostData, true)) {
                // set up response for unsuccessful request
                $response = new Response(false, 400, 'Request body is not valid JSON.!', null, false);
                $response->send();
                exit();
            }

            // Check if post request contains mandatory fields
            if (!isset($jsonData['host']['name']) || !isset($jsonData['host']['version']) || !isset($jsonData['host']['local_ip']) || !isset($jsonData['host']['gateway_ip']) || !isset($jsonData['host']['mac'])) {

                $response = new Response(false, 400, null, null, false);

                (!isset($jsonData['host']['name']) ? $response->addMessage('Host name field is mandatory and must be provided.') : false);

                (!isset($jsonData['host']['version']) ? $response->addMessage('Version field is mandatory and must be provided.') : false);

                (!isset($jsonData['host']['local_ip']) ? $response->addMessage('Local IP field is mandatory and must be provided.') : false);

                (!isset($jsonData['host']['gateway_ip']) ? $response->addMessage('Gateway IP field is mandatory and must be provided.') : false);

                (!isset($jsonData['host']['mac']) ? $response->addMessage('Mac Address field is mandatory and must be provided.') : false);

                $response->send();
                exit;
            }

            // create new array with data, if non mandatory fields not provided then set to null
            // $host = array();

            $host = new Host(null, $jsonData['host']['name'], $jsonData['host']['version'], $jsonData['host']['gateway_ip'], $jsonData['host']['local_ip'], $jsonData['host']['mac']);

            // $hostId = $host->getID();
            $hostname = $host->getHostname();
            $version = $host->getVersion();
            $gatewayIp = $host->getGatewayIp();
            $localIp = $host->getLocalIp();
            $mac = $host->getMac();

            // Check if the given version exists 
            // and insert new version if none
            $query = $writeDB->prepare('SELECT id, name, description, active FROM tbl_versions WHERE name = :version');
            $query->bindParam(':version', $version, PDO::PARAM_STR);
            $query->execute();

            // get row count
            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                $query = $writeDB->prepare('INSERT INTO tbl_versions (id, name) VALUES (NULL, :version)');
                $query->bindParam(':version', $version, PDO::PARAM_STR);
                $query->execute();

                $lastVersionId = $writeDB->lastInsertId();

                $query = $writeDB->prepare('SELECT id, name, description, active FROM tbl_versions WHERE id = :version');
                $query->bindParam(':version', $lastVersionId, PDO::PARAM_STR);
                $query->execute();

                // get row count
                $rowCount = $query->rowCount();
            }

            if ($rowCount === 0) {
                # code...
                $response = new Response(false, 500, 'Failed to create version!', null, false);
                $response->send();
                exit;
            }

            $row = $query->fetch(PDO::FETCH_ASSOC); // Get version details from database 

            $returned_versionId = $row['id'];
            $returned_versionName = $row['name'];
            $returned_versionDescription = $row['description'];
            $returned_versionActive = $row['active'];
            // END OF VERSION CHECK SCRIPTS


            $query = $writeDB->prepare('INSERT INTO tbl_hosts (id, name, versionid, mac, local_ip, gateway_ip) VALUES (NULL, :name, :versionid, :mac, INET_ATON(:localip), INET_ATON(:gatewayip))');

            // $query->bindParam(':hostId', $hostId, PDO::PARAM_INT);
            $query->bindParam(':name', $hostname, PDO::PARAM_STR);
            $query->bindParam(':versionid', $returned_versionId, PDO::PARAM_INT);
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

            // Getting the last host from the database
            $query = $readDB->prepare('SELECT tbl_hosts.id AS hostid, tbl_hosts.name, tbl_versions.name as version, tbl_hosts.mac, INET_NTOA(tbl_hosts.local_ip) AS local_ip, INET_NTOA(tbl_hosts.gateway_ip) AS gateway_ip FROM tbl_hosts INNER JOIN tbl_versions ON tbl_hosts.versionid = tbl_versions.id WHERE tbl_hosts.id = :id');
            $query->bindParam(':id', $lastId, PDO::PARAM_INT);
            $query->execute();

            // get row count
            $rowCount = $query->rowCount();

            // make sure that the new host was returned
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
            $response = new Response(false, 500, 'Failed to insert host into database - please check the submitted data.', null, false);
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
