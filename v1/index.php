<?php

require_once '../include/DbHandler.php';
require '.././libs/Slim/Slim.php';

\Slim\Slim::registerAutoloader();

$app = new \Slim\Slim();

$app->post('/register', function () use ($app) {
    // check for required params
    verifyRequiredParams(array('name', 'email', 'password'));

    $response = array();

    // reading post params
    $name = $app->request->post('name');
    $email = $app->request->post('email');
    $password = $app->request->post('password');

    // validating email address
    validateEmail($email);

    $db = new DbHandler();
    $res = $db->createUser($name, $email, $password);

    if ($res == USER_CREATED_SUCCESSFULLY) {
        $response["error"] = false;
        $response["message"] = "You are successfully registered";
    } else if ($res == USER_CREATE_FAILED) {
        $response["error"] = true;
        $response["message"] = "Oops! An error occurred while registereing";
    } else if ($res == USER_ALREADY_EXISTED) {
        $response["error"] = true;
        $response["message"] = "Sorry, this email already existed";
    }
    // echo json response
    echoRespnse(201, $response);
});

$app->post('/login', function () use ($app) {

    // reading post params
    $username = $app->request()->post('username');
    $email = $app->request()->post('email');
    $response = array();

    $db = new DbHandler();
    if ($db->checkLogin($username, $email)) {

        $user_id = $db->get_user_id($username, $email);

        if ($username != NULL) {
            // $response["error"] = false;
            $response["userid"] = $user_id;
            // $response['name'] = $user['name'];
            // $response['email'] = $user['email'];
            // $response['apiKey'] = $user['api_key'];
            // $response['createdAt'] = $user['created_at'];
        } else {
            // unknown error occurred
            $response['error'] = true;
            $response['message'] = "An error occurred. Please try again";
        }
    } else {
        // user credentials are wrong
        $response['error'] = true;
        $response['message'] = 'Login failed. Incorrect credentials';
    }

    echoRespnse(200, $response);
});

$app->post('/inbox', function () use ($app) {
    $response = array();
    $user_id = $app->request->post('userid');
    $db = new DbHandler();

    // fetching all user tasks
    $result = $db->getInboxData($user_id);

    $response["error"] = false;
    $response["data"] = $result;

    echoRespnse(200, $response);
});


$app->post('/sent', function () use ($app) {
    $response = array();
    $user_id = $app->request->post('userid');
    $db = new DbHandler();

    // fetching all user tasks
    $result = $db->getSentData($user_id);

    $response["error"] = false;
    $response["data"] = $result;
    echoRespnse(200, $response);
});


$app->post('/drafts', function () use ($app) {
    $response = array();
    $user_id = $app->request->post('userid');
    $db = new DbHandler();

    // fetching all user tasks
    $result = $db->getDraftsData($user_id);

    $response["error"] = false;
    $response["data"] = array();

    // looping through result and preparing tasks array
    while ($task = $result->fetch_assoc()) {
        $tmp = array();
        $tmp["id"] = $task["id"];
        $tmp["subject"] = $task["subject"];
        $tmp["body"] = $task["body"];
        $tmp["received_date"] = $task["received_date"];
        $tmp["to_user"] = $task["to_username"];
        $tmp["to_email"] = $task["to_email"];
        array_push($response["data"], $tmp);
    }

    echoRespnse(200, $response);
});

$app->post('/trash', function () use ($app) {
    $response = array();
    $user_id = $app->request->post('userid');
    $db = new DbHandler();

    // fetching all user tasks
    $result = $db->getTrashData($user_id);

    $response["error"] = false;

    $response["data"] = array();

    // looping through result and preparing tasks array
    while ($task = $result->fetch_assoc()) {
        $tmp = array();
        $tmp["username"] = $task["username"];
        $tmp["forwarded"] = $task["forwarded"];
        $tmp["subject"] = $task["body"];
        $tmp["received_date"] = $task["created_date"];
        array_push($response["data"], $tmp);
    }

    echoRespnse(200, $response);
});


$app->post('/mt', function () use ($app) {
    $response = array();
    $inbox_ids = $app->request->post('inbox_ids');
    $source = $app->request->post('source');
    $db = new DbHandler();

    // fetching all user tasks
    $db->move_to_trash($inbox_ids, $source);

    $response["error"] = false;
    $response["data"] = array();

    echoRespnse(200, $response);
});

$app->post('/message', function () use ($app) {
    $response = array();
    $message_id = $app->request->post('message_id');
    $from = $app->request->post('from');
    $user_id = $app->request->post('userid');
    $is_read = $app->request->post('is_read');
    $db = new DbHandler();


    $response["error"] = false;
    if($from != 3) {
        $response["data"] = $db->message($message_id, $from, $user_id, $is_read);
    }else{
        $response["data"] = array();
        $result = $db->message($message_id, $from, $user_id, $is_read);

        // looping through result and preparing tasks array
        while ($task = $result->fetch_assoc()) {
            $tmp = array();
            $tmp["subject"] = $task["subject"];
            $tmp["body"] = $task["body"];
            $tmp["received_date"] = $task["received_date"];
            $tmp["to_user"] = $task["to_username"];
            $tmp["to_email"] = $task["to_email"];
            array_push($response["data"], $tmp);
        }
    }

    echoRespnse(200, $response);
});


$app->post('/compose', function () use ($app) {
    $response = array();
    $email = $app->request->post('email');

    $pattern = '/[a-z0-9_\-\+]+@[a-z0-9\-]+\.([a-z]{2,3})(?:\.[a-z]{2})?/i';

    // preg_match_all returns an associative array
    preg_match_all($pattern, $email, $matches);

    $user_id = $app->request->post('userid');
    $subject = $app->request->post('subject');
    $body = $app->request->post('body');
    $forwarded = $app->request->post('forwarded');
    $draft = $app->request->post('draft');
    $attachment = $app->request->post('attachment');
    $draft_id = $app->request->post('draft_id');

    $db = new DbHandler();

    // fetching all user tasks
    $db->compose($user_id, $subject, $body, $matches[0], $forwarded, $draft, $attachment, $draft_id);

    $response["error"] = false;

    echoRespnse(200, $response);
});

$app->post('/reply', function () use ($app) {
    $response = array();

    $user_id = $app->request->post('userid');
    $inbox_ids = $app->request->post('inbox_ids');
    $reply = $app->request->post('reply');
    $attachments = $app->request->post('attachments');


    $db = new DbHandler();

    // fetching all user tasks
    $result = $db->reply($reply, $user_id, $inbox_ids, $attachments);

    $response["error"] = false;

    echoRespnse(200, $response);
});

$app->post('/forward', function () use ($app) {
    $response = array();

    $email = $app->request->post('emails');

    $pattern = '/[a-z0-9_\-\+]+@[a-z0-9\-]+\.([a-z]{2,3})(?:\.[a-z]{2})?/i';

    // preg_match_all returns an associative array
    preg_match_all($pattern, $email, $matches);

    $user_id = $app->request->post('userid');
    $body = $app->request->post('body');
    $inbox_ids = $app->request->post('inbox_ids');


    $db = new DbHandler();

    // fetching all user tasks
    $result = $db->forward($body, $user_id, $inbox_ids, $matches[0]);

    $response["error"] = false;

    echoRespnse(200, $response);
});


$app->post('/sd', function () use ($app) {
    $response = array();

    $user_id = $app->request->post('userid');
    $subject = $app->request->post('subject');
    $body = $app->request->post('body');
    $email = $app->request->post('email');

    $db = new DbHandler();

    // fetching all user tasks
    $result = $db->storeDraft($user_id, $subject, $body, $email);

    $response["error"] = false;

    echoRespnse(200, $response);
});

$app->post('/mt', function () use ($app) {
    $response = array();

    $user_id = $app->request->post('userid');
    $message_id = $app->request->post('message_id');

    $db = new DbHandler();

    // fetching all user tasks
    $result = $db->move_to_trash($message_id, $user_id);

    $response["error"] = false;

    echoRespnse(200, $response);
});

function echoRespnse($status_code, $response)
{
    $app = \Slim\Slim::getInstance();
    // Http response code
    $app->status($status_code);

    // setting response content type to json
    $app->contentType('application/json');

    echo json_encode($response);
}

$app->run();
?>