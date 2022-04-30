<?php

require_once('db.php');
require_once('../model/response.php');

try {
    $writeDB = DB::connectWriteDB();
    $readDB = DB::connectReadDB();

    // $response = new Response();
    // $response->setHttpStatusCode(200);
    // $response->setSuccess(true);
    // $response->addMessage('Database connection ok!');
    // $response->send();

    $response = new Response(true, 200, 'Database connection ok!', null, false, null);
    $response->send();


    exit;

} catch (PDOException $ex) {
    //throw $th;
    // $response = new Response();
    // $response->setHttpStatusCode(500);
    // $response->setSuccess(false);
    // $response->addMessage('Database connection error!');
    // $response->send();

    $response = new Response(false, 500, 'Database connection error!', null, false, null);
    $response->send();

    exit;
}
