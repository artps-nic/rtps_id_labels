<?php
session_start([
    'name' => 'NIC_SESSION',
    'cookie_secure' => 1,
    'cookie_httponly' => 1,
    'cookie_samesite' => 'Lax'
]);
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php", TRUE, 307);
    exit(1);
}

require_once './db_con.php';

$pdo = (new DBManager())->get_postgres_connection();

$service_ids = filter_input(INPUT_POST, 'service_ids', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);

$from_date = filter_input(INPUT_POST, 'from_date', FILTER_VALIDATE_REGEXP, array(
    "options" => array("regexp" => "/^\d{4}-\d{2}-\d{2}$/")
));

$to_date = filter_input(INPUT_POST, 'to_date', FILTER_VALIDATE_REGEXP, array(
    "options" => array("regexp" => "/^\d{4}-\d{2}-\d{2}$/")
));


if (!empty($service_ids) && !empty($from_date) && !empty($to_date)) {
    // All Ok.
    $t1 = DBManager::APPLICATION_TABLE;
    $t2 = DBManager::TRACK_TABLE;

    $params = explode(',', $service_ids);
    $in = str_repeat('?,', count($params) - 1) . '?';
    $params[] = $from_date;
    $params[] = $to_date;

    $sql = "INSERT INTO {$t2} SELECT appl_ref_no, base_service_id FROM {$t1} WHERE initiated_data IS NOT NULL AND base_service_id::VARCHAR IN ($in) AND date(submission_date) BETWEEN ? AND ?";

    $stmt = $pdo->prepare($sql);
    // var_dump($stmt->queryString); die;

    if ($stmt->execute($params) && $stmt->rowCount()) {
        redirect_with_message("{$stmt->rowCount()} applications scheduled to get inserted into MIS.Applications");
    } else {
        redirect_with_message('No applications found.');
    }
} else {
    // Invalid input
    redirect_with_message('Invalid Input(s)!');
}


function redirect_with_message($error = '')
{
    $_SESSION['error'] = $error;
    header("Location: ../push_data.php", TRUE, 307);
    exit(1);
}
