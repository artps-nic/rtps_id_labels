<?php
session_start([
    'name' => 'NIC_SESSION',
    'cookie_secure' => 1,
    'cookie_httponly' => 1,
    'cookie_samesite' => 'Lax'
]);

require_once '../db_con.php';
require_once '../util/utility.php';

$pdo = (new DBManager())->get_postgres_connection();
$util = new Utility();

try {
    $t1 = DBManager::ID_LABEL_MAPPINGS_TABLE;
    $t2 = DBManager::ATTRIBUTE_MAST_TABLE;
    $t3 = DBManager::APPLICATION_TABLE;

    $sql = <<<EOD
                SELECT DISTINCT base_service_id from {$t3}
                WHERE base_service_id::VARCHAR IN (

                SELECT t1.service_id FROM

                (select service_id, count(*) as total from {$t1} 
                GROUP by service_id HAVING service_id IS NOT NULL) t1
                LEFT JOIN
                (select LEFT(service_id::VARCHAR, 4) as service_id, count(*) AS total FROM {$t2}
                where attribute_input_type not in ('label', 'button', 'declareText', 'blank') and attribute_label != ' '
                GROUP by service_id HAVING service_id IS NOT NULL) t2
                ON t1.service_id = t2.service_id

                WHERE t2.total > t1.total

                ) ORDER BY base_service_id
            EOD;

    $stmt = $pdo->query($sql);
    // $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $rows = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

    // $rows = array_map(function ($arr) {
    //     return $arr['service_name'] . " ($arr[base_service_id])";
    // }, $rows);

    // print_r($rows);
    // die;

    $util->send_response(200, $rows);
    // $util->send_response(500, 'Test error');
} catch (\PDOException $ex) {
    $util->send_response(500, $ex->getMessage());
}
