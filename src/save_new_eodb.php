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

$pdo = (new DBManager())->get_postgres_connection_eodb();

if(isset($_POST['B1'])){
    $service_id=$_POST['service_id'];
    $service_name=$_POST['service_name'];
    $service_launch_date=$_POST['service_launch_date'];

    if((strlen(trim($service_id))!=0) && (strlen(trim($service_name))!=0) && (strlen(trim($service_launch_date))!=0)){

        $sql = "INSERT INTO ".DBManager::EODB_SERVICE." (base_service_id, service_name, launch_date) values (?,?,?)";
        $stmt = $pdo->prepare($sql);
        if($stmt->execute([$service_id, $service_name, $service_launch_date])){
            $_SESSION['suc'] = 'Service added successfully.. <hr>';
            header("Location: ../manage_eodb_services.php", TRUE, 307);
        }
    }
    else{
        $_SESSION['error'] = 'Oops.. Something went wrong <hr>';
        header("Location: ../manage_eodb_services.php", TRUE, 307);
    }
}
if(isset($_POST['B2'])){
    $service_id=$_POST['service_id'];
    $service_name=$_POST['service_name'];
    $service_launch_date=$_POST['service_launch_date'];
    $ref_id=$_POST['ref_id'];
    if((strlen(trim($service_id))!=0) && (strlen(trim($service_name))!=0) && (strlen(trim($service_launch_date))!=0)){

        $sql = "UPDATE ".DBManager::EODB_SERVICE." set base_service_id=?, service_name=?, launch_date=? where base_service_id=?";
        $stmt = $pdo->prepare($sql);
        if($stmt->execute([$service_id, $service_name, $service_launch_date,$ref_id])){
            $_SESSION['suc'] = 'Service updated successfully.. <hr>';
            header("Location: ../manage_eodb_services.php", TRUE, 307);
        }
    }
    else{
        $_SESSION['error'] = 'Oops.. Something went wrong <hr>';
        header("Location: ../manage_eodb_services.php", TRUE, 307);
    }
}
?>
