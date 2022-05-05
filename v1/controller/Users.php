<?php

require_once('db.php');
require_once('../model/Response.php');

try { // Setup database connection
    $writeDB = DB::connectWriteDB();

    // $readDB = DB::connectReadDB();
} catch (PDOException $ex) { // Catch any database connection errors.
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
    $response = new Response(false, 405, 'Request method is not allowed!', null, false);
    $response->send();
    exit;

    $userId = $_GET['id'];

    if ($userId == '' || !is_numeric($userId)) {
        $response = new Response(false, 400, 'User ID cannot be blank or must be numeric.', null, false);
        $response->send();
        exit;
    }
} elseif (empty($_GET)) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            //code...
            if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
                // set up response for unsuccessful request
                $response = new Response(false, 400, 'Content Type header not set to JSON!', null, false);
                $response->send();
                exit();
            }

            // Get the POST request body as the posted data will be in JSON format
            $rawPostData = file_get_contents('php://input');

            if (!$jsonData = json_decode($rawPostData)) {
                // set up response for unsuccessful request
                $response = new Response(false, 400, 'Request body is not valid JSON!', null, false);
                $response->send();
                exit();
            }

            // Check if mandatory fields (username, password) are supplied
            if (!isset($jsonData->username) || !isset($jsonData->password)) {
                $response = new Response(false, 400);

                (!isset($jsonData->username) ? $response->addMessage('Username not supplied.') : false);
                (!isset($jsonData->password) ? $response->addMessage('Password not supplied.') : false);

                $response->send();
                exit();
            }

            // Check for empty strings.
            if (strlen($jsonData->firstname) < 1 || strlen($jsonData->firstname) > 255 || strlen($jsonData->surname) < 1 || strlen($jsonData->surname) > 255 || strlen($jsonData->username) < 6 || strlen($jsonData->username) > 255 || strlen($jsonData->password) < 8 || strlen($jsonData->password) > 255 || !preg_match('@[A-Z]@', $jsonData->password) || !preg_match('@[a-z]@', $jsonData->password) || !preg_match('@[0-9]@', $jsonData->password) || !preg_match('@[^\w]@', $jsonData->password)) {
                $response = new Response(false, 400);

                (strlen($jsonData->firstname) < 1 ? $response->addMessage('First name cannot be blank.') : false);
                (strlen($jsonData->firstname) > 255 ? $response->addMessage('First name cannot be greater than 255 characters.') : false);

                (strlen($jsonData->surname) < 1 ? $response->addMessage('Surname cannot be blank.') : false);
                (strlen($jsonData->surname) > 255 ? $response->addMessage('Surname name cannot be greater than 255 characters.') : false);

                (strlen($jsonData->username) < 6 ? $response->addMessage('Username cannot be less than 6 characters.') : false);
                (strlen($jsonData->username) > 255 ? $response->addMessage('Username name cannot be greater than 255 characters.') : false);

                // (strlen($jsonData->password) < 8 ? $response->addMessage('Password cannot be less than 8 characters.') : false);
                // (strlen($jsonData->password) > 255 ? $response->addMessage('Password cannot be greater than 255 characters.') : false);

                if (strlen($jsonData->password) < 8 || strlen($jsonData->password) > 255 || !preg_match('@[A-Z]@', $jsonData->password) || !preg_match('@[a-z]@', $jsonData->password) || !preg_match('@[0-9]@', $jsonData->password) || !preg_match('@[^\w]@', $jsonData->password)) {
                    $response->addMessage('Password should be at least 8 characters in length and should include at least one upper case letter, one number, and one special character.');
                };


                $response->send();
                exit();
            }

            // Remove whitespaces at beginning and end from the submitted data
            $firstname = trim($jsonData->firstname);
            $surname = trim($jsonData->surname);
            $username = trim($jsonData->username);
            $password = $jsonData->password;

            try { // Try to insert the user into the database

                // Check if the username already exists in the database
                $query = $writeDB->prepare('SELECT id FROM tbl_users WHERE username = :username');
                $query->bindParam(':username', $username, PDO::PARAM_STR);
                $query->execute();

                $rowCount = $query->rowCount();

                if ($rowCount !== 0) { // Throw an error if the username exists
                    $response = new Response(false, 409, 'Username already exists.', null, false);
                    $response->send();
                    exit();
                } // End check for username existence

                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

                $query = $writeDB->prepare('INSERT INTO tbl_users (firstname, surname, username, password) values (:firstname, :surname, :username, :password)');

                $query->bindParam(':firstname', $firstname, PDO::PARAM_STR);
                $query->bindParam(':surname', $surname, PDO::PARAM_STR);
                $query->bindParam(':username', $username, PDO::PARAM_STR);
                $query->bindParam(':password', $hashedPassword, PDO::PARAM_STR);

                $query->execute();

                $rowCount = $query->rowCount();

                if ($rowCount === 0) {
                    $response = new Response(false, 500, 'There was an issue creating a user account - please try again in a few moments!', null, false);
                    $response->send();
                    exit();
                }

                $lastUserId = $writeDB->lastInsertId();

                $returnData = array();

                $returnData['user_id'] = $lastUserId;
                $returnData['firstname'] = $firstname;
                $returnData['surname'] = $surname;
                $returnData['username'] = $username;

                $response = new Response(true, 201, 'User created.', $returnData, false);
                $response->send();
                exit();

                /** */
            } catch (PDOException $ex) {
                error_log('Database query error - ' . $ex, 0);
                $response = new Response(false, 500, 'There was an issue creating a user account - please try again in a few moments!', null, false);
                $response->send();
                exit();
            }

            /** */
        } catch (PDOException $ex) {
            //throw $th;
            $response = new Response(false, 500, 'Failed to insert user into database - please check the submitted data.', null, false);
            $response->send();
            exit();
        }
    } else {
        $response = new Response(false, 405, 'Request method is not allowed!', null, false);
        $response->send();
        exit;
    }
}
