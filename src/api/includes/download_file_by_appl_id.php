<?php

// Unnecessary declearations
global $db;
global $util;

$appl_id = trim($_POST['appl_id'] ?? '');
if (empty($appl_id)) {
    $res = "APPL_ID not found.";

    $util->send_response(400, $res);
}
$portal = (trim($_POST['portal'] ?? '') === 'EODB') ? 'EODB' : null;
if (empty($portal)) {
    $res = "PORTAL not found.";

    $util->send_response(400, $res);
}
$doc_type = trim($_POST['doc_type'] ?? '');
if (empty($doc_type)) {
    $res = "DOC_TYPE not found.";

    $util->send_response(400, $res);
}

// Get the file_path with specified doc_tye

$pdo = $db->get_postgres_connection_new(DBManager::EODB_CONFIGURE);
$stmt = $pdo->prepare("select file_name from schm_sp.application_cert where application_id = ? and application_cert_flag = ?");
$stmt->bindParam(1, $appl_id, PDO::PARAM_INT);
$stmt->bindParam(2, $doc_type, PDO::PARAM_STR);
$stmt->execute();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);


// Read all the filed content

foreach ($rows as $key => &$val) {
    if (is_file($val['file_name'])) {

        if (is_readable($val['file_name'])) {
            $val['file_content'] = base64_encode(file_get_contents($val['file_name']));
        } else {
            $val['file_content'] = null;
            $val['error'] = 'File not readable';
        }
    } else {
        $val['file_content'] = null;
        $val['error'] = 'File not found';
    }
}

// print_r($rows);
$util->send_response(200, $rows);
