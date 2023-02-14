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


require_once '../vendor/autoload.php';
require_once './db_con.php';

define('MAX_FILE_SIZE', 3 * 1024 * 1024);   //  3MB
define('ALLOWED_FILE_TYPES', ['text/csv', 'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
define('UPLOAD_DIR', dirname(__FILE__, 2) . DIRECTORY_SEPARATOR . 'uploads');

// db connection
$pdo = (new DBManager())->get_postgres_connection();

/* Handling file uploads */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['attribute_file'])) {
    if (!empty($_FILES['attribute_file']['error'])) {
        // error
        redirect_with_message($_FILES['attribute_file']['error']);
    }

    if ($_FILES['attribute_file']['size'] > MAX_FILE_SIZE) {
        // error
        redirect_with_message('File size is too large');
    }

    if (!in_array($_FILES['attribute_file']['type'], ALLOWED_FILE_TYPES)) {
        // error
        redirect_with_message('File type is invalid');
    }

    // All Ok.
    $tmp_name = $_FILES['attribute_file']['tmp_name'];
    $filepath = UPLOAD_DIR . DIRECTORY_SEPARATOR . $_FILES['attribute_file']['name'];

    if (!move_uploaded_file($tmp_name, $filepath)) {
        redirect_with_message('Error in uploading file');
    }


    // Get service ID & service Type
    $service_id = filter_input(INPUT_POST, 'service_id', FILTER_SANITIZE_NUMBER_INT);
    $service_type = filter_input(INPUT_POST, 'service_type', FILTER_SANITIZE_STRING);

    if (!empty($service_id) && !empty($service_type)) {
        // input validation
        $service_id = trim($service_id);
        $service_id = filter_var($service_id, FILTER_VALIDATE_INT);

        $service_type = strtoupper(trim($service_type));
        $service_type = filter_var($service_type, FILTER_VALIDATE_REGEXP, [
            'options' => ['regexp' => '/^(RTPS|EODB)$/']
        ]);

        if ($service_id !== false && $service_type !== false) {
            // Insert Attributes into ID_LABEL_MAPPINGS table.

            /*  $output = null;
            $retval = null;

            exec('php -r "var_dump(empty([]));" ', $output, $retval);

            echo '<pre>';
            print_r([$output, $retval]);
            echo '</pre>'; */

            require_once './insert_keys.php';
            $output = insert_id_labels($filepath, $service_id, $service_type);

            /** Using Post-Redirect-Get method **/

            $_SESSION['data'] = $output;
            header('Location: ../upload_status.php', true, 303);
            exit;
        } else {
            // invalid service id
            redirect_with_message('Invalid Service ID or Service Type');
        }
    } else {
        // invalid service id
        redirect_with_message('Invalid Service ID');
    }
}


function redirect_with_message($error = '')
{
    $_SESSION['error'] = $error;
    header("Location: ../upload_attribute_list.php", TRUE, 307);
    exit(1);
}
