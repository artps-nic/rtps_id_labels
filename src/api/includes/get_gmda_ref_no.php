<?php

global $db;
global $util;

if (empty($params)) {
    throw new Exception("Invalid Application ID.", 1);
}
$pdo = $db->get_postgres_connection_new(DBManager::RTPS_CONFIGURE);
if ($params['sign_role'] == "CTZN") {
    $stmt = $pdo->prepare("SELECT t1.spdv_appl_ref_no as appl_ref_no, t1.spdv_beneficiary_name as applicant_name, t2.mobile_no as mobile_no
        FROM schm_sp.spe_service_application_details t1, schm_sp.m_adm_sign t2 
        WHERE t1.spdi_beneficiary_id = t2.user_id
        AND spdv_appl_ref_no iLike 'RTPS-PPBP/%'
        AND t1.spdv_status = 'D'
        AND t2.sign_role = 'CTZN'
        AND t2.mobile_no = :sign_no ");
} else if ($params['sign_role'] == "PFC") {
    $stmt = $pdo->prepare("SELECT t1.spdv_appl_ref_no as appl_ref_no, t1.spdv_beneficiary_name as applicant_name, t2.mobile_no as mobile_no
        FROM schm_sp.spe_service_application_details t1, schm_sp.m_adm_sign t2 
        WHERE t1.spdi_beneficiary_id = t2.user_id
        AND spdv_appl_ref_no iLike 'RTPS-PPBP/%'
        AND t1.spdv_status = 'D'
        AND t2.sign_role = 'KIOSK'
        AND t2.sign_no = :sign_no ");
} else if ($params['sign_role'] == "CSC") {
    $stmt = $pdo->prepare("SELECT spdv_appl_ref_no as appl_ref_no, 'CSC Operator' as applicant_name from schm_sp.spe_service_application_details 
        WHERE spdv_status = 'D'
        AND  spdi_application_id IN (
        SELECT  application_id from sp_custom.application_processing_json 
        WHERE department_id = 1477
        AND kiosk_registration_no = :sign_no 
        )");
}
$stmt->execute(['sign_no' => $params['sign_no']]);
// $stmt->debugDumpParams();
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
$res['applications'] = $rows;
$util->send_response(200, $res);
