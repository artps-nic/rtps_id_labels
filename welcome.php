<?php
session_start([
    'name' => 'NIC_SESSION',
    'cookie_secure' => 1,
    'cookie_httponly' => 1,
    'cookie_samesite' => 'Lax'
]);
if (!isset($_SESSION['user']) || $_SESSION['user']['username'] !== 'rtps_admin') {
    header("Location: ./index.php", TRUE, 303);
    exit(1);
}

require_once './src/db_con.php';
$pdo = (new DBManager())->get_postgres_connection();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RTPS | ID-LABELS</title>
    <link rel="stylesheet" href="./assets/style.css">
    <script src="./assets/script.js" defer></script>
</head>

<body>
    <nav class="main-nav">
        <button>Check New/Modified Attributes</button>
        <a href="./upload_attribute_list.php">Upload Attribute List</a> |
        <a href="./push_data.php">Push Data Manually</a> |
        <a href="./manage_eodb_services.php">Manage EODB Intgr. Services</a> |
        <a href="./office_mappings.php">Office/Office Mappings</a> |
        <a href="./district_mappings.php">Office/District Mappings</a> |
        <a href="./rtps_districts.php">District Mappings</a> |
        <a href="./src/logout.php" title="Logout">
            <img src="./assets/logout2.png" alt="Logout" width="32">
        </a>
    </nav>

    <main>
        <!-- FLASH Messages -->
        <?php if (isset($_SESSION['error'])) : ?>
            <p class="error"><?= $_SESSION['error'] ?></p>

            <?php unset($_SESSION['error']); ?>

        <?php endif; ?>


        <form action="<?= $_SERVER['PHP_SELF']; ?>" method="GET">

            <label for="type">
                <strong>Select Services:</strong>
            </label>
            <select name="type" id="type" required>
                <option value="">--- please select ---</option>
                <option value="all">All Services</option>
                <option value="new">Newly Added</option>
                <option value="old">Old</option>
            </select>

            <button type="submit">Submit</button>
            <button type="reset">Reset</button>

        </form>
        <br>
        <hr> <br>

        <section>

            <?php
            // input sanitization
            $type = filter_input(INPUT_GET, 'type', FILTER_SANITIZE_STRING, FILTER_FLAG_STRIP_LOW | FILTER_FLAG_STRIP_HIGH);

            if (!empty($type)) {
                // input validation
                $type = trim($type);
                $type = filter_var($type, FILTER_VALIDATE_REGEXP, array(
                    "options" => array("regexp" => "/^all$|^new$|^old$/")
                ));


                if ($type !== false) {
                    // valid input
                    switch ($type) {
                        case 'all':
                            $sql = "SELECT DISTINCT base_service_id, service_name FROM " . DBManager::APPLICATION_TABLE . " WHERE initiated_data IS NOT NULL ORDER BY service_name";
                            break;

                        case 'new':
                            $sql = "SELECT DISTINCT base_service_id, service_name FROM " . DBManager::APPLICATION_TABLE . " WHERE initiated_data IS NOT NULL AND base_service_id::VARCHAR NOT IN (SELECT DISTINCT service_id FROM " . DBManager::ID_LABEL_MAPPINGS_TABLE . " ) ORDER BY service_name";
                            break;

                        case 'old':
                            $sql = "SELECT DISTINCT base_service_id, service_name FROM " . DBManager::APPLICATION_TABLE . " WHERE initiated_data IS NOT NULL AND base_service_id::VARCHAR IN (SELECT DISTINCT service_id FROM " . DBManager::ID_LABEL_MAPPINGS_TABLE . " ) ORDER BY service_name";
                            break;
                    }

                    $stmt = $pdo->query($sql);
                    $rows = $stmt->fetchAll();
            ?>

                    <em>Total Services found: <?= count($rows) ?></em>
                    <table>
                        <caption>Download attribute list for services</caption>
                        <thead>
                            <tr>
                                <th>Service ID</th>
                                <th colspan="2">Service Name</th>
                            </tr>
                        </thead>
                        <tbody>

                            <?php foreach ($rows as $row) : ?>

                                <tr>
                                    <td><?= $row['base_service_id'] ?></td>
                                    <td><?= $row['service_name'] ?></td>
                                    <td>
                                        <a href="<?= './src/download.php?service-id=' . $row['base_service_id'] ?>">Download</a>
                                    </td>
                                </tr>

                            <?php endforeach; ?>



                        </tbody>
                    </table>


            <?php    }
            }
            ?>

        </section>

    </main>

    <div class="model hide">
        <button>X</button>
        <div class="header">
        </div>
        <div class="body">
        </div>
    </div>

</body>

</html>