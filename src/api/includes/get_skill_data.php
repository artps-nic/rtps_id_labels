<?php
// Get SKILL data by by registration_no , submission_date
// registration_no = '3548256/02/2022', submission_date = '2022-03-28'

global $db;
global $util;

$pdo = $db->get_postgres_connection_new(DBManager::RTPS_CONFIGURE);
$reg_no = trim($_GET['reg_no'] ?? '');
$sub_date = trim($_GET['sub_date'] ?? '');

if (empty($reg_no) || empty($sub_date)) {

    throw new Exception("Missing inputs REG_NO / SUB_DATE", 1);
}

// Get appl_ref_no & bese_service_id by quering with registration_no & submission_date
// PHP multiline string HEREDOC & NOWDOC
$stmt = $pdo->prepare(<<<QUERY

    select appl_ref_no, base_service_id from sp_custom.application_processing_json
    where department_id = '2193' and appl_status = 'D'
    and submission_date::date = ?
    and COALESCE(
        initiated_data->'application_form_attributes'->>'122792',
        initiated_data->'application_form_attributes'->>'122866',
        initiated_data->'application_form_attributes'->>'121822',
        initiated_data->'application_form_attributes'->>'121584',
        initiated_data->'application_form_attributes'->>'128408',
        initiated_data->'application_form_attributes'->>'133297',
        initiated_data->'application_form_attributes'->>'133304',
        initiated_data->'application_form_attributes'->>'133284',
        initiated_data->'application_form_attributes'->>'133053',
        initiated_data->'application_form_attributes'->>'133093',
        initiated_data->'application_form_attributes'->>'132884',
        initiated_data->'application_form_attributes'->>'133143',
        initiated_data->'application_form_attributes'->>'133158'
    
    ) = ?

    QUERY);
$stmt->bindParam(1, $sub_date, PDO::PARAM_STR);
$stmt->bindParam(2, $reg_no, PDO::PARAM_STR);
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (empty($row)) {
    $res = "No record found for REG_NO: $reg_no & SUB_DATE: $sub_date";
    $util->send_response(404, $res);
} else {
    $res = process_data_for_track($row['appl_ref_no'] ?? '', $row['base_service_id'] ?? '');
    $util->send_response(200, $res);
}
