<?php
date_default_timezone_set('Asia/Kolkata');
session_start([
    'name' => 'NIC_SESSION',
    'cookie_secure' => 1,
    'cookie_httponly' => 1,
    'cookie_samesite' => 'Lax'
]);
require_once '../db_con.php';
require_once '../util/utility.php';
require_once './format_converter.php';
require_once './add_current_pending_task.php';
require_once './status_ibt_calc.php';
require_once '../../vendor/autoload.php';

define('RTPS_AUTH_TOKEN', '|0VW?z,-w2w"6;{b8v}K6A5+Fdf@l-');
define('RTPS_API_REQ_METHODS', ['GET', 'POST', 'PUT', 'PATCH',]);
define('RTPS_SECRET', 'rtpsapi#!@');


$db = new DBManager();
$util = new Utility();


try {
    /* 
    GET /external_apis.php/get_track_data HTTP/1.1
    Authorization: Bearer {token}
    */

    // Check auth token
    $token = getallheaders()['Authorization'] ?? '';
    $token = explode(' ', $token)[1] ?? '';
    if (empty($token) ||  $token !== RTPS_AUTH_TOKEN) {
        $util->send_response(401, 'Unauthorized Access.');
    }

    // GET/POST APIs
    if (in_array($_SERVER['REQUEST_METHOD'], RTPS_API_REQ_METHODS, true)) {
        $func_name = explode('/', preg_replace('/^\/+/imu', '', trim($_SERVER['PATH_INFO'])))[0];

        // Check if the function exists in this File
        if (function_exists($func_name)) {
            call_user_func($func_name, $_GET);
        }
        // Otherwise, check if file exists, then import it
        elseif (is_file(__DIR__ .  DIRECTORY_SEPARATOR . "includes/{$func_name}.php")) {

            require_once __DIR__ . DIRECTORY_SEPARATOR . "includes/{$func_name}.php";
        } else {
            $util->send_response(404, 'Resource Not Found.');
        }
    } else {
        $util->send_response(405, 'Only GET & POST Method are Allowed.');
    }
} catch (\Exception $ex) {
    $util->send_response(400, $ex->getMessage());
}


/* 
* API Functions
*/


function download_file()
{
    global $util;
    $file_path = trim($_POST['file_path'] ?? '');
    if (empty($file_path)) {
        $res = "FILE_PATH not found.";

        $util->send_response(400, $res);
    }

    $portal = (trim($_POST['portal'] ?? '') === 'EODB') ? 'EODB' : null;
    if (empty($portal)) {
        $res = "PORTAL not found.";

        $util->send_response(400, $res);
    }

    if (is_file($file_path)) {

        if (is_readable($file_path)) {
            $res['file_content'] = base64_encode(file_get_contents($file_path));
        } else {
            $res = "File not readable at PATH: {$file_path}";
        }
    } else {
        $res = "File not found at PATH: {$file_path}";
    }

    $util->send_response(200, $res);
}

// Get track data by appl_ref_no
function get_track_data()
{
    global $util;
    $app_ref_no = trim($_GET['appl_ref_no'] ?? '');
    if (empty($app_ref_no)) {
        $res = "APPL_REF_NO not found.";

        $util->send_response(400, $res);
    }

    $service_id = trim($_GET['service_id'] ?? '');
    if (empty($service_id)) {
        $res = "SERVICE_ID not found.";

        $util->send_response(400, $res);
    }

    $portal = (trim($_GET['portal'] ?? '') === 'EODB') ? 'EODB' : null;

    $res = process_data_for_track($app_ref_no, $service_id, $portal);

    if (empty($res)) {
        $res = "No application found for {$app_ref_no}, SERVICE_ID: {$service_id}";
    }

    $util->send_response(200, $res);
}

// Get tinyURL data from appl_id
function get_tiny_url()
{
    global $util;
    $application_id = trim($_GET['application_id'] ?? '');
    if (empty($application_id)) {
        $res = "APPLICATION_ID not found.";

        $util->send_response(400, $res);
    }

    $service_id = trim($_GET['service_id'] ?? '');
    if (empty($service_id)) {
        $res = "SERVICE_ID not found.";

        $util->send_response(400, $res);
    }

    $portal = (trim($_GET['portal'] ?? '') === 'EODB') ? 'EODB' : null;

    $res = find_tiny_urls($application_id, $service_id, $portal);

    if (empty($res)) {
        $res = "No Tiny URLs found for {$application_id}, SERVICE_ID: {$service_id}";
    }

    $util->send_response(200, $res);
}



function get_sp_data()
{
    global $util;

    $json_str = trim(file_get_contents('php://input'));   // Raw input data as string
    $content_type = getallheaders()['Content-Type'] ?? '';
    if (empty($json_str) || $content_type != 'application/json') {

        $res = "Invalid Request";

        $util->send_response(400, $res);
    }


    $data = json_decode($json_str, true); // PHP assoc Array
    $app_ref_no = trim($data['app_ref_no'] ?? '');
    $secret = trim($data['secret'] ?? '');

    if (empty($app_ref_no) || $secret != RTPS_SECRET) {
        $res = "Invalid Request";

        $util->send_response(400, $res);
    }

    // Check both EODB/RTPS applications
    $res = process_data_for_track_v2($app_ref_no);

    if (empty($res)) {
        $res = "No records found with this app ref no: {$app_ref_no}";

        $util->send_response(404, $res);
    }

    $util->send_response(200, $res);
}


function get_mis_data()
{
    global $util;
    global $db;

    $mongo = $db->get_mongo_connection();

    // localhost/NIC/rtps_id_labels/src/api/external_apis.php/get_mis_data?app_ref_no=RTPS-MCC/2023/9031902&debug=true
    // First Check if it's a debug request

    parse_str($_SERVER['QUERY_STRING'], $output);
    if (boolval($output['debug'] ?? '')) {

        $document = $mongo->selectDatabase('mis')->selectCollection('applications')->findOne(
            ['initiated_data.appl_ref_no' => $output['app_ref_no'] ?? ''],
            [
                'projection' => [
                    '_id' => 0
                ],
            ]
        );
        print_r($document ?? 'Record not found');
        exit(0);
    }


    $json_str = trim(file_get_contents('php://input'));   // Raw input data as string
    $content_type = getallheaders()['Content-Type'] ?? '';
    if (empty($json_str) || $content_type != 'application/json') {

        $res = "Invalid Request";


        $util->send_response(400, $res);
    }

    $data = json_decode($json_str, true); // PHP assoc Array
    $app_ref_no = trim($data['app_ref_no'] ?? '');
    $secret = trim($data['secret'] ?? '');

    if (empty($app_ref_no) || $secret != RTPS_SECRET) {
        $res = "Invalid Request";

        $util->send_response(400, $res);
    }


    $document = $mongo->selectDatabase('mis')->selectCollection('applications')->findOne(
        ['initiated_data.appl_ref_no' => $app_ref_no ?? ''],
        [
            'projection' => [
                '_id' => 0,

            ],
        ]
    );
    if (empty($document)) {
        $res = "No records found with this app ref no: {$app_ref_no}";

        $util->send_response(404, $res);
    }

    $submission_datetm = ($document['initiated_data']['submission_date'])->toDateTime();
    $submission_datetm->setTimezone(new \DateTimeZone('Asia/Kolkata'));

    // Format the data
    $result = array(
        'initiated_data' => [
            'appl_ref_no' => $document['initiated_data']['appl_ref_no'],
            'submission_date' => $submission_datetm->format('Y-m-d H:i:s'),
            'status' => $document['initiated_data']['appl_status'],
            'applicant_name' => $document['initiated_data']['attribute_details']['applicant_name'] ?? '',
            'service_name' => $document['initiated_data']['service_name'],
            'service_timeline' => $document['initiated_data']['service_timeline'] ?? '',
            'service_id' => $document['initiated_data']['base_service_id'],
        ],
        'execution_data' => array_map(function ($val) {
            $dto = (empty($val['task_details']['executed_time']) ? $val['task_details']['received_time'] : $val['task_details']['executed_time'])->toDateTime();
            $dto->setTimezone(new \DateTimeZone('Asia/Kolkata'));

            return [
                'processed_by' => $val['task_details']['task_name'] ?? '',
                'action_taken' => $val['official_form_details']['action'] ?? '',
                'remarks' => $val['official_form_details']['remarks'] ?? '',
                'processing_time' => $dto->format('Y-m-d H:i:s'),
            ];
        }, array_reverse(iterator_to_array($document['execution_data']))),

        'source' => 'MIS',
    );


    // For APDCL services add consumer No.
    if ($document['initiated_data']['service_id'] === 'apdcl1') {

        $result['initiated_data']['application_no'] = $document['initiated_data']['attribute_details']['application_no'] ?? '';
        $result['initiated_data']['consumer_no'] = '';
        if (!$result($document['execution_data'])) {
            $exec_data = $document['execution_data'][0];
            $result['initiated_data']['consumer_no'] = $exec_data['task_details']['consNo'] ?? '';
        }
    }



    $util->send_response(200, $result);
}




/* 
* Internal Functions
*/
function process_data_for_track($app_ref_no, $service_id, $portal = null)
{
    global $db;

    $mongo = $db->get_mongo_connection();

    if (is_null($portal)) {
        # RTPS

        $pdo_labels_mapping = $db->get_postgres_connection_new(DBManager::RTPS_PROD);

        $pdo = (get_service_type($service_id) === 'RTPS') ? $db->get_postgres_connection_new(DBManager::RTPS_CONFIGURE) : $db->get_postgres_connection_new(DBManager::EODB_CONFIGURE);
    } elseif ($portal === 'EODB') {
        # EODB

        $pdo_labels_mapping = $db->get_postgres_connection_new(DBManager::EODB_PROD);

        $pdo = $db->get_postgres_connection_new(DBManager::EODB_CONFIGURE);
    }

    // 1. Get base_service_id
    $stmt = $pdo->prepare("select CAST (base_service_id AS VARCHAR), CAST (application_id AS VARCHAR), CAST (service_id AS VARCHAR), initiated_data, execution_data from sp_custom.application_processing_json where appl_ref_no = ? AND base_service_id = ? AND initiated_data IS NOT NULL");
    $stmt->bindParam(1, $app_ref_no, PDO::PARAM_STR);
    $stmt->bindParam(2, $service_id, PDO::PARAM_INT);
    $stmt->execute();
    $appl = $stmt->fetch(PDO::FETCH_ASSOC);
    // var_dump($appl); die;

    if (empty($appl)) {
        return null;
    }

    // 2. Create KEY-MAPPINGS data
    $stmt = $pdo_labels_mapping->query("SELECT attrb_id, attrb_label, service_id, is_nested FROM sp_custom.id_label_mappings where service_id = '{$appl['base_service_id']}' ");
    $rows = $stmt->fetchAll();

    $keys_dict = array();

    foreach ($rows as $row) {
        $keys_dict[$row['attrb_id']] = array($row['service_id'], $row['attrb_label'], $row['is_nested']);
    }

    // 3. Get the application & Process
    $data_arr = convert_data($appl['initiated_data'], $appl['execution_data'], $appl['base_service_id']);

    /* ID Label Replacements */
    foreach ($data_arr as $key => $value) {

        if ($key == 'initiated_data' and is_array($value)) {

            // Clean submission_location
            $data_arr['initiated_data']['submission_location'] = clean_data($value['submission_location']);

            foreach (array_keys($value['attribute_details']) as $id) {

                if (array_key_exists($id, $keys_dict) and $keys_dict[$id][0] == $appl['base_service_id']) {

                    // Get the key/id's label
                    $new_key = $keys_dict[$id][1];

                    // Check the key is nested or not.

                    if ($keys_dict[$id][2]) {
                        // Multi-valued attribute

                        $final_array = array();

                        $arr = json_decode($value['attribute_details'][$id], true);

                        if (!$arr) {
                            // Replace the id with it's label

                            $data_arr['initiated_data']['attribute_details'][$new_key] = clean_data($value['attribute_details'][$id]);

                            // Also, delete the old key
                            unset($data_arr['initiated_data']['attribute_details'][$id]);

                            continue;
                        }

                        // How many nested objects?
                        for ($i = 1; $i <= (int) $arr['f1']; $i++) {
                            array_push($final_array, array());
                        }

                        foreach (array_keys($arr) as $k) {
                            if ($k !== 'f1') {
                                $old_key = explode("_", $k)[0];
                                $index = explode("_", $k)[1];

                                // ID needed?
                                // if (!array_key_exists('id', $final_array[((int) $index) - 1])) {
                                //   $final_array[((int) $index) - 1]['id'] = (int) $index;
                                // }

                                if (array_key_exists($old_key, $keys_dict) and $keys_dict[$old_key][0] == $appl['base_service_id']) {
                                    $new_co_key = $keys_dict[$old_key][1];

                                    // Which array to choose?
                                    $final_array[((int) $index) - 1][$new_co_key] = clean_data($arr[$k]);
                                } else {
                                    // when attribute id/label mapping not found
                                    $final_array[((int) $index) - 1][$old_key] = clean_data($arr[$k]);
                                }
                            }
                        }

                        $data_arr['initiated_data']['attribute_details'][$new_key] = $final_array;
                    } else {
                        // Single-value attribute

                        $data_arr['initiated_data']['attribute_details'][$new_key] = clean_data($value['attribute_details'][$id]);
                    }

                    // Delete the old key
                    unset($data_arr['initiated_data']['attribute_details'][$id]);
                }
            }

            // Check if applicant_name and mobile_number fileds exist in attribute_details
            if (!array_key_exists('applicant_name', $data_arr['initiated_data']['attribute_details'])) {

                $data_arr['initiated_data']['attribute_details']['applicant_name'] = 'not avaiable';
            }

            if (!array_key_exists('mobile_number', $data_arr['initiated_data']['attribute_details'])) {

                $data_arr['initiated_data']['attribute_details']['mobile_number'] = 'not avaiable';
            }
        } elseif ($key == 'execution_data') {
            // For execution data array
            // Change 'official_form_details' keys

            for ($i = 0; $i < count($value); $i++) {
                $exec_data = $value[$i];

                // 'official_form_details' may be NULL
                if (!is_array($exec_data['official_form_details'])) {
                    continue;
                }

                foreach (array_keys($exec_data['official_form_details']) as $id) {

                    if (array_key_exists($id, $keys_dict) and $keys_dict[$id][0] == $appl['base_service_id']) {

                        // Get the key/id's label
                        $new_key = $keys_dict[$id][1];

                        if ($keys_dict[$id][2]) {
                            // Multi-valued attribute

                            $final_array = array();

                            $arr = json_decode($exec_data['official_form_details'][$id], true);

                            if (!$arr) {
                                continue;
                            }

                            // How many nested objects?
                            for ($j = 1; $j <= (int) $arr['f1']; $j++) {
                                array_push($final_array, array());
                            }

                            foreach (array_keys($arr) as $k) {
                                if ($k !== 'f1') {
                                    $old_key = explode("_", $k)[0];
                                    $index = explode("_", $k)[1];

                                    // ID needed?
                                    // if (!array_key_exists('id', $final_array[((int) $index) - 1])) {
                                    //   $final_array[((int) $index) - 1]['id'] = (int) $index;
                                    // }

                                    if (array_key_exists($old_key, $keys_dict) and $keys_dict[$old_key][0] == $appl['base_service_id']) {
                                        $new_co_key = $keys_dict[$old_key][1];

                                        // Which array to choose?
                                        $final_array[((int) $index) - 1][$new_co_key] = clean_data($arr[$k]);
                                    }
                                }
                            }

                            $data_arr['execution_data'][$i]['official_form_details'][$new_key] = $final_array;
                        } else {

                            // Single-value attribute
                            // Remove '~' from data value only when attribute label not 'action'

                            if ($new_key === 'action') {

                                $actionID = explode('~', $exec_data['official_form_details'][$id])[0];

                                if (array_key_exists($actionID, ACTIONS_IDS)) {

                                    $data_arr['execution_data'][$i]['official_form_details'][$new_key] = ACTIONS_IDS[$actionID];
                                } else {
                                    // IF action not found, make it 'Forward' as default

                                    $data_arr['execution_data'][$i]['official_form_details'][$new_key] = ACTIONS_IDS['9'];
                                }
                            } else {
                                $data_arr['execution_data'][$i]['official_form_details'][$new_key] = clean_data($exec_data['official_form_details'][$id]);
                            }
                        }

                        // Delete the old key
                        unset($data_arr['execution_data'][$i]['official_form_details'][$id]);
                    }
                }
            }
        }
    }

    // Adding service timeline
    $document = $mongo->mis->services->findOne(['service_id' => $appl['base_service_id']]);
    $data_arr['initiated_data']['service_timeline'] = $document['service_timeline'] ?? 0;

    // Adding Application Current status
    $stmt = $pdo->query("SELECT spdv_status FROM schm_sp.spe_service_application_details WHERE spdi_application_id = {$appl['application_id']}");
    $data_arr['initiated_data']['appl_status'] = $stmt->fetch()['spdv_status'] ?? 'F';

    // Calculate IN TIME / BEYOND TIME 
    calc_appl_status_time($data_arr);


    // Add current_pending_task info for PENDING applications
    if ($data_arr['initiated_data']['appl_status'] != 'D' && $data_arr['initiated_data']['appl_status'] != 'R') {
        add_current_pending_task($pdo, $data_arr, $mongo);
    }

    // Add Certificates
    add_certs($data_arr, $pdo);

    // print_r($data_arr); die();

    if (is_array($data_arr['initiated_data']['enclosure_details'])) {

        // Add Enclosure Paths
        $stmt = $pdo->query("SELECT * FROM schm_sp.spe_service_application_annexure WHERE spdi_application_id = {$appl['application_id']}");
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($data_arr['initiated_data']['enclosure_details'] as $key => &$encl) {     // pass by reference
            $arr_key = array_keys($encl);

            // print_r($arr_key); die;

            foreach ($rows as $k => $db_row) {
                $arr_val = array_values($db_row);

                $res = array_intersect($arr_key, $arr_val);

                if (!empty($res) && sizeof($res) === sizeof($arr_key)) {
                    $encl['file_path']  = $db_row['storage_file_name'] ?? '';
                    break;

                    // print_r(array_intersect($arr_key, $arr_val)); die;
                }
            }

            $encl['file_path'] = $encl['file_path'] ?? '';
        }
    }

    return $data_arr;
}

function find_tiny_urls($application_id, $service_id, $portal = null)
{
    global $db;

    if (is_null($portal)) {
        # RTPS

        $pdo = (get_service_type($service_id) === 'RTPS') ? $db->get_postgres_connection_new(DBManager::RTPS_CONFIGURE) : $db->get_postgres_connection_new(DBManager::EODB_CONFIGURE);
    } elseif ($portal === 'EODB') {
        # EODB

        $pdo = $db->get_postgres_connection_new(DBManager::EODB_CONFIGURE);
    }


    // Sanitize & Validate
    $application_id = filter_var($application_id, FILTER_VALIDATE_INT);

    if (empty($application_id)) {
        throw new Exception("Invalid Application ID.", 1);
    }

    $res = ['application_id' => $application_id,];

    $stmt = $pdo->prepare("select tiny_url, appl_valid_status from schm_sp.application_wise_tiny_url WHERE application_id = ?");
    $stmt->bindParam(1, $application_id, PDO::PARAM_INT);
    $stmt->execute();
    $appl = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $res['appl_wise'] = $appl;

    $stmt = $pdo->prepare("select lengthy_url, tiny_url, task_id from schm_sp.spe_service_tiny_url WHERE application_id = ?");
    $stmt->bindParam(1, $application_id, PDO::PARAM_INT);
    $stmt->execute();
    $appl = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $res['service_wise'] = $appl;

    // var_dump($res); die;
    return $res;
}

function get_service_type($service_id)
{
    global $db;
    $pdo_rtps_prod = $db->get_postgres_connection_new(DBManager::RTPS_PROD);

    $stmt = $pdo_rtps_prod->query("select DISTINCT service_type from sp_custom.id_label_mappings where service_id = '{$service_id}'");
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (empty($row)) {
        throw new Exception("SERVICE_ID Not Found in DB.", 1);
    }

    $service_type = trim($row['service_type']);

    // var_dump($service_type); die;

    return $service_type;
}


function process_data_for_track_v2($app_ref_no)
{
    global $db;
    $mongo = $db->get_mongo_connection();    // MONGODB prod

    // First check RTPS DB
    $pdo = $db->get_postgres_connection_new(DBManager::RTPS_PROD);
    $stmt = $pdo->prepare("select appl_ref_no, submission_date, service_name, CAST (base_service_id AS VARCHAR), CAST (application_id AS VARCHAR), CAST (service_id AS VARCHAR), initiated_data, execution_data from sp_custom.application_processing_json where appl_ref_no = ? AND initiated_data IS NOT NULL");
    $stmt->bindParam(1, $app_ref_no, PDO::PARAM_STR);
    $stmt->execute();
    $appl = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!empty($appl)) {
        $pdo_labels_mapping = $db->get_postgres_connection_new(DBManager::RTPS_PROD);
    } else {
        // Check on EODB DB

        $pdo = $db->get_postgres_connection_new(DBManager::EODB_PROD);
        $stmt = $pdo->prepare("select appl_ref_no, submission_date, service_name, CAST (base_service_id AS VARCHAR), CAST (application_id AS VARCHAR), CAST (service_id AS VARCHAR), initiated_data, execution_data from sp_custom.application_processing_json where appl_ref_no = ? AND initiated_data IS NOT NULL");
        $stmt->bindParam(1, $app_ref_no, PDO::PARAM_STR);
        $stmt->execute();
        $appl = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!empty($appl)) {
            $pdo_labels_mapping = $db->get_postgres_connection_new(DBManager::EODB_PROD);
        } else {
            // No record found
            return null;
        }
    }

    // 2. Create KEY-MAPPINGS data
    $stmt = $pdo_labels_mapping->query("SELECT attrb_id, attrb_label, service_id, is_nested FROM sp_custom.id_label_mappings where service_id = '{$appl['base_service_id']}' ");
    $rows = $stmt->fetchAll();

    $keys_dict = array();

    foreach ($rows as $row) {
        $keys_dict[$row['attrb_id']] = array($row['service_id'], $row['attrb_label'], $row['is_nested']);
    }

    // 3. Get the application & Process
    $data_arr = convert_data($appl['initiated_data'], $appl['execution_data'], $appl['base_service_id']);

    /* ID Label Replacements */
    foreach ($data_arr as $key => $value) {

        if ($key == 'initiated_data' and is_array($value)) {

            // Clean submission_location
            $data_arr['initiated_data']['submission_location'] = clean_data($value['submission_location']);

            foreach (array_keys($value['attribute_details']) as $id) {

                if (array_key_exists($id, $keys_dict) and $keys_dict[$id][0] == $appl['base_service_id']) {

                    // Get the key/id's label
                    $new_key = $keys_dict[$id][1];

                    // Check the key is nested or not.

                    if ($keys_dict[$id][2]) {
                        // Multi-valued attribute

                        $final_array = array();

                        $arr = json_decode($value['attribute_details'][$id], true);

                        if (!$arr) {
                            // Replace the id with it's label

                            $data_arr['initiated_data']['attribute_details'][$new_key] = clean_data($value['attribute_details'][$id]);

                            // Also, delete the old key
                            unset($data_arr['initiated_data']['attribute_details'][$id]);

                            continue;
                        }

                        // How many nested objects?
                        for ($i = 1; $i <= (int) $arr['f1']; $i++) {
                            array_push($final_array, array());
                        }

                        foreach (array_keys($arr) as $k) {
                            if ($k !== 'f1') {
                                $old_key = explode("_", $k)[0];
                                $index = explode("_", $k)[1];

                                // ID needed?
                                // if (!array_key_exists('id', $final_array[((int) $index) - 1])) {
                                //   $final_array[((int) $index) - 1]['id'] = (int) $index;
                                // }

                                if (array_key_exists($old_key, $keys_dict) and $keys_dict[$old_key][0] == $appl['base_service_id']) {
                                    $new_co_key = $keys_dict[$old_key][1];

                                    // Which array to choose?
                                    $final_array[((int) $index) - 1][$new_co_key] = clean_data($arr[$k]);
                                } else {
                                    // when attribute id/label mapping not found
                                    $final_array[((int) $index) - 1][$old_key] = clean_data($arr[$k]);
                                }
                            }
                        }

                        $data_arr['initiated_data']['attribute_details'][$new_key] = $final_array;
                    } else {
                        // Single-value attribute

                        $data_arr['initiated_data']['attribute_details'][$new_key] = clean_data($value['attribute_details'][$id]);
                    }

                    // Delete the old key
                    unset($data_arr['initiated_data']['attribute_details'][$id]);
                }
            }

            // Check if applicant_name and mobile_number fileds exist in attribute_details
            if (!array_key_exists('applicant_name', $data_arr['initiated_data']['attribute_details'])) {

                $data_arr['initiated_data']['attribute_details']['applicant_name'] = 'not avaiable';
            }

            if (!array_key_exists('mobile_number', $data_arr['initiated_data']['attribute_details'])) {

                $data_arr['initiated_data']['attribute_details']['mobile_number'] = 'not avaiable';
            }
        } elseif ($key == 'execution_data') {
            // For execution data array
            // Change 'official_form_details' keys

            for ($i = 0; $i < count($value); $i++) {
                $exec_data = $value[$i];

                // 'official_form_details' may be NULL
                if (!is_array($exec_data['official_form_details'])) {
                    continue;
                }

                foreach (array_keys($exec_data['official_form_details']) as $id) {

                    if (array_key_exists($id, $keys_dict) and $keys_dict[$id][0] == $appl['base_service_id']) {

                        // Get the key/id's label
                        $new_key = $keys_dict[$id][1];

                        if ($keys_dict[$id][2]) {
                            // Multi-valued attribute

                            $final_array = array();

                            $arr = json_decode($exec_data['official_form_details'][$id], true);

                            if (!$arr) {
                                continue;
                            }

                            // How many nested objects?
                            for ($j = 1; $j <= (int) $arr['f1']; $j++) {
                                array_push($final_array, array());
                            }

                            foreach (array_keys($arr) as $k) {
                                if ($k !== 'f1') {
                                    $old_key = explode("_", $k)[0];
                                    $index = explode("_", $k)[1];

                                    // ID needed?
                                    // if (!array_key_exists('id', $final_array[((int) $index) - 1])) {
                                    //   $final_array[((int) $index) - 1]['id'] = (int) $index;
                                    // }

                                    if (array_key_exists($old_key, $keys_dict) and $keys_dict[$old_key][0] == $appl['base_service_id']) {
                                        $new_co_key = $keys_dict[$old_key][1];

                                        // Which array to choose?
                                        $final_array[((int) $index) - 1][$new_co_key] = clean_data($arr[$k]);
                                    }
                                }
                            }

                            $data_arr['execution_data'][$i]['official_form_details'][$new_key] = $final_array;
                        } else {

                            // Single-value attribute
                            // Remove '~' from data value only when attribute label not 'action'

                            if ($new_key === 'action') {

                                $actionID = explode('~', $exec_data['official_form_details'][$id])[0];

                                if (array_key_exists($actionID, ACTIONS_IDS)) {

                                    $data_arr['execution_data'][$i]['official_form_details'][$new_key] = ACTIONS_IDS[$actionID];
                                } else {
                                    // IF action not found, make it 'Forward' as default

                                    $data_arr['execution_data'][$i]['official_form_details'][$new_key] = ACTIONS_IDS['9'];
                                }
                            } else {
                                $data_arr['execution_data'][$i]['official_form_details'][$new_key] = clean_data($exec_data['official_form_details'][$id]);
                            }
                        }

                        // Delete the old key
                        unset($data_arr['execution_data'][$i]['official_form_details'][$id]);
                    }
                }
            }
        }
    }

    // Adding service timeline
    $document = $mongo->mis->services->findOne(['service_id' => $appl['base_service_id']]);
    $data_arr['initiated_data']['service_timeline'] = $document['service_timeline'] ?? 0;

    // Adding Application Current status
    $stmt = $pdo->query("SELECT spdv_status FROM schm_sp.spe_service_application_details WHERE spdi_application_id = {$appl['application_id']}");
    $data_arr['initiated_data']['appl_status'] = $stmt->fetch()['spdv_status'] ?? 'F';


    // Add current_pending_task info for PENDING applications
    // if ($data_arr['initiated_data']['appl_status'] != 'D' && $data_arr['initiated_data']['appl_status'] != 'R') {
    //     add_current_pending_task($pdo, $data_arr, $mongo);
    // }


    // Format the data
    $result = array(
        'initiated_data' => [
            'appl_ref_no' => $appl['appl_ref_no'],
            'submission_date' => $appl['submission_date'],
            'status' => $data_arr['initiated_data']['appl_status'] ?? '',
            'applicant_name' => $data_arr['initiated_data']['attribute_details']['applicant_name'] ?? '',
            'service_name' => $appl['service_name'],
            'service_timeline' => $data_arr['initiated_data']['service_timeline'] ?? '',
            'service_id' => $appl['base_service_id'],
        ],
        'execution_data' => array_map(function ($val) {
            $dto = \DateTimeImmutable::createFromFormat('d-m-Y H:i:s', $val['task_details']['executed_time'], new \DateTimeZone('Asia/Kolkata'));

            return [
                'processed_by' => $val['task_details']['task_name'] ?? '',
                'action_taken' => $val['official_form_details']['action'] ?? '',
                'remarks' => $val['official_form_details']['remarks'] ?? '',
                'processing_time' => $dto->format('Y-m-d H:i:s'),
            ];
        }, array_reverse($data_arr['execution_data'])),

        'source' => 'SERVICE_PLUS',
    );

    return $result;
}
