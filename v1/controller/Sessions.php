<?php

require_once('db.php');
require_once('../model/Response.php');

try {
    $writeDB = DB::connectWriteDB();
} catch (PDOException $ex) {
    error_log('Connection error: ' . $ex, 0);
    $response = new Response(false, 500, 'Database connection error.');
    $response->send();
    exit();
}


if (array_key_exists('sessionid', $_GET)) {
    $sessionid = $_GET['sessionid'];

    if ($sessionid === '' || !is_numeric($sessionid)) {
        $response = new Response(false, 400);
        ($sessionid == '' ? $response->addMessage('Session ID cannot be blank.') : false);
        (!is_numeric($sessionid) == '' ? $response->addMessage('Session ID must be numeric.') : false);
        $response->send();
        exit();
    }

    if (!isset($_SERVER['HTTP_AUTHORIZATION']) || strlen($_SERVER['HTTP_AUTHORIZATION']) < 1) {
        $response = new Response(false, 401);
        (!isset($_SERVER['HTTP_AUTHORIZATION']) ? $response->addMessage('Access token is missing from the header.') : false);
        (strlen($_SERVER['HTTP_AUTHORIZATION']) < 1 ? $response->addMessage('Access token cannot be blank.') : false);
        $response->send();
        exit();
    }

    $accessToken = $_SERVER['HTTP_AUTHORIZATION'];

    if ($_SERVER['REQUEST_METHOD'] === 'DELETE') {

        try {
            $query = $writeDB->prepare('DELETE FROM tbl_sessions WHERE id = :sessionid AND accesstoken = :accesstoken');
            $query->bindParam(':sessionid', $sessionid, PDO::PARAM_INT);
            $query->bindParam(':accesstoken', $accessToken, PDO::PARAM_STR);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                $response = new Response(false, 400, 'Failed to log out of this session using access token provided.');
                $response->send();
                exit();
            }

            $returnData = array();
            $returnData['session_id'] = intval($sessionid);

            $response = new Response(true, 200, 'Logged out.');
            $response->setData($returnData);
            $response->send();
            exit();
        } catch (PDOException $ex) {
            $response = new Response(false, 500, 'There was an issue logging out - please try again.');
            $response->send();
            exit();
        }
    } elseif ($_SERVER['REQUEST_METHOD'] === 'PATCH') {

        if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
            $response = new Response(false, 400, 'Content type header not set to JSON.');
            $response->send();
            exit();
        }

        $rawPATCHData = file_get_contents('php://input');

        if (!$jsonData = json_decode($rawPATCHData)) {
            $response = new Response(false, 400, 'Request body is not valid JSON.');
            $response->send();
            exit();
        }

        // check if patch request contains access token
        if (!isset($jsonData->refresh_token) || strlen($jsonData->refresh_token) < 1) {
            $response = new Response(false, 400);
            (!isset($jsonData->refresh_token) ? $response->addMessage("Refresh Token not supplied") : false);
            (strlen($jsonData->refresh_token) < 1 ? $response->addMessage("Refresh Token cannot be blank") : false);
            $response->send();
            exit;
        }

        try {
            $refreshToken = $jsonData->refresh_token;

            $query = $writeDB->prepare('SELECT tbl_sessions.id AS sessionid, tbl_sessions.userid AS userid, accesstoken, refreshtoken, active, login_attempts, accesstokenexpiry, refreshtokenexpiry FROM tbl_sessions, tbl_users WHERE tbl_users.id = tbl_sessions.userid AND tbl_sessions.id = :sessionid AND tbl_sessions.accesstoken = :accesstoken AND tbl_sessions.refreshtoken = :refreshtoken');
            $query->bindParam(':sessionid', $sessionid, PDO::PARAM_INT);
            $query->bindParam(':accesstoken', $accessToken, PDO::PARAM_STR);
            $query->bindParam(':refreshtoken', $refreshToken, PDO::PARAM_STR);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                $response = new Response(false, 401, 'Access token or refresh token is incorrect for session id.');
                $response->send();
                exit();
            }

            $row = $query->fetch(PDO::FETCH_ASSOC);

            $returned_sessionid = $row['sessionid'];
            $returned_userid = $row['userid'];
            $returned_accesstoken = $row['accesstoken'];
            $returned_refreshtoken = $row['refreshtoken'];
            $returned_useractive = $row['active'];
            $returned_loginattempts = $row['login_attempts'];
            $returned_accesstokenexpiry = $row['accesstokenexpiry'];
            $returned_refreshtokenexpiry = $row['refreshtokenexpiry'];

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

            if (strtotime($returned_refreshtokenexpiry) < time()) {
                $response = new Response(false, 401, 'Refresh token has expired - please log in again.');
                $response->send();
                exit();
            }

            $accessToken = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)) . time());
            $refreshToken = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)) . time());

            $accesstoken_expiry_seconds = 1200;
            $refreshtoken_expiry_seconds = 1209600;

            $query = $writeDB->prepare('UPDATE tbl_sessions SET accesstoken = :accesstoken, accesstokenexpiry = date_add(NOW(), INTERVAL :accesstokenexpiryseconds SECOND), refreshtoken = :refreshtoken, refreshtokenexpiry = date_add(NOW(), INTERVAL :refreshtokenexpiryseconds SECOND) WHERE id = :sessionid AND userid = :userid AND accesstoken = :returnedaccesstoken AND refreshtoken = :returnedrefreshtoken');
            $query->bindParam(':userid', $returned_userid, PDO::PARAM_INT);
            $query->bindParam(':sessionid', $returned_sessionid, PDO::PARAM_INT);
            $query->bindParam(':accesstoken', $accessToken, PDO::PARAM_STR);
            $query->bindParam(':accesstokenexpiryseconds', $accesstoken_expiry_seconds, PDO::PARAM_INT);
            $query->bindParam(':refreshtoken', $refreshToken, PDO::PARAM_STR);
            $query->bindParam(':refreshtokenexpiryseconds', $refreshtoken_expiry_seconds, PDO::PARAM_INT);
            $query->bindParam(':returnedaccesstoken', $returned_accesstoken, PDO::PARAM_STR);
            $query->bindParam(':returnedrefreshtoken', $returned_refreshtoken, PDO::PARAM_STR);
            $query->execute();

            $rowCount = $query->rowCount();

            if ($rowCount === 0) {
                $response = new Response(false, 401, 'Session could not be refreshed - please log in again.');
                $response->send();
                exit();
            }

            $returnedData = array();
            $returnedData['session_id'] = $returned_sessionid;
            $returnedData['access_token'] = $accessToken;
            $returnedData['access_token_expiry'] = $accesstoken_expiry_seconds;
            $returnedData['refresh_token'] = $refreshToken;
            $returnedData['refresh_token_expiry'] = $refreshtoken_expiry_seconds;

            $response = new Response(true, 200, 'Session refreshed.');
            $response->setData($returnedData);
            $response->send();
            exit();
        } catch (PDOException $ex) {
            $response = new Response(false, 500, 'There was an issue refreshing session - please login again.');
            $response->send();
            exit();
        }
    } else {
        $response = new Response(false, 405, 'Request method not allowed.');
        $response->send();
        exit();
    }

    /** */
} elseif (empty($_GET)) {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        $response = new Response(false, 404, 'Request method not allowed!');
        $response->send();
        exit();
    }

    // Sleep for delaying automated attacks
    sleep(1);

    if ($_SERVER['CONTENT_TYPE'] !== 'application/json') {
        // set up response for unsuccessful request
        $response = new Response(false, 400, 'Content Type header not set to JSON!');
        $response->send();
        exit();
    }

    // Get the POST request body as the posted data will be in JSON format
    $rawPostData = file_get_contents('php://input');

    // Check if the submitted data is valid json.
    if (!$jsonData = json_decode($rawPostData)) {
        // set up response for unsuccessful request
        $response = new Response(false, 400, 'Request body is not valid JSON!');
        $response->send();
        exit();
    }

    // Check if mandatory fields are supplied.
    if (!isset($jsonData->username) || !isset($jsonData->password)) {
        $response = new Response(false, 400);

        (!isset($jsonData->username) ? $response->addMessage('Username not supplied!') : false);
        (!isset($jsonData->password) ? $response->addMessage('Password not supplied!') : false);

        $response->send();
        exit();
    }

    // Username and password validation
    if (strlen($jsonData->username) < 1 || strlen($jsonData->username) > 255 || strlen($jsonData->password) < 1 || strlen($jsonData->password) > 255) {
        $response = new Response(false, 400);

        (strlen($jsonData->username) < 1 ? $response->addMessage('Username cannot be blank!') : false);
        (strlen($jsonData->username) > 255 ? $response->addMessage('Username cannot be greater than 255 characters!') : false);

        (strlen($jsonData->password) < 1 ? $response->addMessage('Password cannot be blank!') : false);
        (strlen($jsonData->password) > 255 ? $response->addMessage('Password cannot be greater than 255 characters!') : false);

        $response->send();
        exit();
    }


    try {
        // Check if username exists in the database
        $username = $jsonData->username;
        $password = $jsonData->password;

        $query = $writeDB->prepare('SELECT id, firstname, surname, username, password, active, login_attempts, type, hostid FROM tbl_users WHERE username = :username');
        $query->bindParam(':username', $username, PDO::PARAM_STR);
        $query->execute();

        $rowCount = $query->rowCount();

        if ($rowCount === 0) {
            $response = new Response(false, 401, 'Username or password is incorrect.');
            $response->send();
            exit();
        }

        $row = $query->fetch(PDO::FETCH_ASSOC);

        $returned_id = $row['id'];
        $returned_firstname = $row['firstname'];
        $returned_surname = $row['surname'];
        $returned_username = $row['username'];
        $returned_password = $row['password'];
        $returned_active = $row['active'];
        $returned_login_attempts = $row['login_attempts'];
        $returned_type = $row['type'];
        $returned_hostid = $row['hostid'];

        // Check if the user is active
        if ($returned_active !== 'Y') {
            $response = new Response(false, 401, 'User account is not active.');
            $response->send();
            exit();
        }

        // Count the number of login attempts
        if ($returned_login_attempts > 3) {
            $response = new Response(false, 401, 'User account is currently locked out.');
            $response->send();
            exit();
        }

        // Verifiy the given password with the password hash from the database
        if (!password_verify($password, $returned_password)) {
            // If the passwords dont match, update login attempts +1
            $query = $writeDB->prepare('UPDATE tbl_users SET login_attempts = login_attempts+1 WHERE id = :id');
            $query->bindParam(':id', $returned_id, PDO::PARAM_INT);
            $query->execute();

            // Send a generic error response to not tell that the password is incorrect
            $response = new Response(false, 401, 'Username or password is incorrect.');
            $response->send();
            exit();
        }

        // Create new refresh & access token
        $accesstoken = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)) . time());
        $refreshtoken = base64_encode(bin2hex(openssl_random_pseudo_bytes(24)) . time());

        $accesstoken_expriry_seconds = 1200;
        $refreshtoken_expriry_seconds = 1209600; // 14 days valid 

        //code...
    } catch (PDOException $ex) {
        $response = new Response(false, 500, 'There was an issue logging in - please try again.');
        $response->send();
        exit();
    }

    try {
        $writeDB->beginTransaction();

        $query = $writeDB->prepare('UPDATE tbl_users SET login_attempts = 0 WHERE id = :id');
        $query->bindParam(':id', $returned_id, PDO::PARAM_INT);
        $query->execute();

        $query = $writeDB->prepare('INSERT INTO tbl_sessions (userid, accesstoken, accesstokenexpiry, refreshtoken, refreshtokenexpiry) values (:userid, :accesstoken, date_add(NOW(), INTERVAL :accesstokenexpiryseconds SECOND), :refreshtoken, date_add(NOW(), INTERVAL :refreshtokenexpiryseconds SECOND))');

        $query->bindParam(':userid', $returned_id, PDO::PARAM_INT);
        $query->bindParam(':accesstoken', $accesstoken, PDO::PARAM_STR);
        $query->bindParam(':accesstokenexpiryseconds', $accesstoken_expriry_seconds, PDO::PARAM_INT);
        $query->bindParam(':refreshtoken', $refreshtoken, PDO::PARAM_STR);
        $query->bindParam(':refreshtokenexpiryseconds', $refreshtoken_expriry_seconds, PDO::PARAM_INT);

        $query->execute();

        // Get the last inserted id
        $lastSessionId = $writeDB->lastInsertId();

        // Save temporary data to the database 
        $writeDB->commit();

        $returnData = array();

        $returnData['host_id'] = $returned_hostid;
        $returnData['session_id'] = intval($lastSessionId);
        $returnData['accesstoken'] = $accesstoken;
        $returnData['accesstoken_expires_in'] = $accesstoken_expriry_seconds;
        $returnData['refreshtoken'] = $refreshtoken;
        $returnData['refreshtoken_expires_in'] = $refreshtoken_expriry_seconds;

        $response = new Response(true, 201, 'Login ok!', $returnData);
        $response->send();
        exit();

        /** */
    } catch (PDOException $ex) {
        $writeDB->rollBack();
        $response = new Response(false, 500, 'There was an issue logging in - please try again.');
        $response->send();
        exit();
    }

    /** */
} else {
    $response = new Response(false, 404, 'Endpoint not found!');
    $response->send();
    exit();
}
