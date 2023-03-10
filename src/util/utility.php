<?php
date_default_timezone_set('Asia/Calcutta');

class Utility
{
    /*** Logs will be written daywise. ****/
    public function log_info($msg)
    {
        $base_dir = dirname(__FILE__, 2) . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR;
        $file_name = 'info_log_' . date('Y-m-d');
        $myfile = fopen($base_dir . $file_name . '.txt', 'a') or die('Unable to open file!');

        $message = '[' . date('Y-m-d@h-i-s') . '] : ' . $msg . PHP_EOL;

        fwrite($myfile, $message);
        fclose($myfile);
    }

    /* Send JSON response */
    public function send_response($code = 200, $data)
    {
        header('Content-type:application/json;charset=utf-8');
        http_response_code($code);
        echo json_encode(array(
            'status' => ($code == 200) ? true : false,
            'data' => $data
        ), JSON_UNESCAPED_UNICODE |  JSON_UNESCAPED_SLASHES);
        exit();
    }

    public function create_user($username, $password, $mongo)
    {
        $collection = $mongo->nic_db->id_label_users;
        $insertOneResult = $collection->insertOne([
            'username' => trim($username),
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'is_active' => true,
            'created_at' => new MongoDB\BSON\UTCDateTime(strtotime("now") * 1000),
        ]);

        if ($insertOneResult->getInsertedCount()) {
            return $insertOneResult->getInsertedId();
        }
        return false;
    }
}
