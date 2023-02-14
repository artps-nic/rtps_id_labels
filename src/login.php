<?php
session_start([
    'name' => 'NIC_SESSION',
    'cookie_secure' => 1,
    'cookie_httponly' => 1,
    'cookie_samesite' => 'Lax'
]);

require_once '../vendor/autoload.php';
require_once './db_con.php';
require_once './util/utility.php';

$mongo = (new DBManager())->get_mongo_connection();
$util = new Utility();

// Sanitize inputs
$username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);
$password = filter_input(INPUT_POST, 'password', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);

// Validate inputs
$userdata = filter_var_array(
    [
        'username' => $username,
        'password' => $password
    ],
    [
        'username' => array(
            'filter' => FILTER_VALIDATE_REGEXP,
            'options'  => array("regexp" => "/^[a-z_A-Z0-9]{3,}$/"),
        ),
        'password' => array(
            'filter' => FILTER_VALIDATE_REGEXP,
            'options'  => array("regexp" => "/^[a-z_A-Z0-9- !@#$%^&*()]{7,}$/"),
        ),
    ]
);

if (!empty($userdata['username']) && !empty($userdata['password'])) {
    // All Ok.
    // var_dump($util->create_user($userdata['username'], $userdata['password'], $mongo)); die;

    $collection = $mongo->nic_db->id_label_users;
    $user = $collection->findOne(['username' => trim($userdata['username']), 'is_active' => true]);

    if ($user && password_verify($userdata['password'], $user['password'])) {
        // log the user in
        session_regenerate_id(true);
        $_SESSION['user'] = ['uid' => strval($user['_id']), 'username' => $user['username']];

        header("Location: ../welcome.php", TRUE, 307);
        exit();
    } else {
        redirect_with_message('Invalid Credentials!');
    }
} else {
    // Invalid input
    redirect_with_message('Invalid Credentials!');
}


function redirect_with_message($error = '')
{
    $_SESSION['error'] = $error;
    header("Location: ../index.php", TRUE, 307);
    exit(1);
}
