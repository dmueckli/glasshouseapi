<?php

require_once('weatherData.php');
require_once('../controller/db.php');
require_once('response.php');
// require_once('');

try {
    //code...
    $weatherData = new WeatherData(1, null, 61.09999847, 38, 19.20000076, 18.73158646, '2022-04-18 00:55:10');



    header('Content-type: application/json; charset=utf-8');

    $json = array();
    $json['weatherData'] = $weatherData->returnAsArray();



    if ($json['weatherData']['id'] == 1 && $json['weatherData']['hostId'] == null && $json['weatherData']['humidity'] == 61.09999847 && $json['weatherData']['soil moisture'] == 38 && $json['weatherData']['temperature'] == 19.20000076 && $json['weatherData']['heat index'] == 18.73158646 && $json['weatherData']['time'] == '2022-04-18 00:55:10') {
        # code...
        $response = new Response(true, 200, 'Model tests: ok!', $json, false);
        $response->send();

        exit;
    } else {
        # code...
        $response = new Response(false, 500, 'Model tests: failed!', $json, false);
        $response->send();

        exit;
    }

} catch (WeatherDataException $ex) {
    //throw $th;
    echo "Error:" . $ex->getMessage();
}
