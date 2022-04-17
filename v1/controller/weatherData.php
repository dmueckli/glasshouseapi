<?php

require_once('db.php');
require_once('../model/response.php');
require_once('../model/weatherData.php');

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
            $query = $readDB->prepare('SELECT id, host_id, humidity, soil_moisture, temperature, heat_index, time FROM tbl_weatherdata WHERE id = :id');
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
                $weatherdata = new WeatherData($row['id'], $row['host_id'], $row['humidity'], $row['soil_moisture'], $row['temperature'], $row['heat_index'], $row['time']);

                $weatherDataArray[] = $weatherdata->returnAsArray();
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
} else {
    # code...
    $response = new Response(false, 404, 'URL Endpoint not found!', null, false);
    $response->send();
    exit;
}
