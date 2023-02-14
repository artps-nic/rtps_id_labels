<?php
session_start([
    'name' => 'NIC_SESSION',
    'cookie_secure' => 1,
    'cookie_httponly' => 1,
    'cookie_samesite' => 'Lax'
]);
if (!isset($_SESSION['user']) || $_SESSION['user']['username'] !== 'rtps_admin') {
    header("Location: ./index.php", TRUE, 307);
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
</head>

<body>
    <nav>
        <a href="./welcome.php">Download Attribute List</a> |
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

        <form action="./src/data_push_status.php" method="post">
            <fieldset class="clearfix">
                <legend>Push Data Manually</legend>
                <input type="hidden" name="service_ids" value="" required>

                <label for="from_date">From Date : </label>
                <input type="date" name="from_date" id="from_date" min="2013-01-01" max="<?= date('Y-m-d'); ?>" required>

                <label for="to_date">To Date : </label>
                <input type="date" name="to_date" id="to_date" min="2013-01-01" max="<?= date('Y-m-d'); ?>" required>

                <input type="submit" value="Submit">
            </fieldset>
        </form>

        <h3 class="my-h3">Please Select Services:</h3>

        <section>
            <?php
            $sql = "SELECT DISTINCT base_service_id, service_name FROM " . DBManager::APPLICATION_TABLE . " WHERE initiated_data IS NOT NULL AND base_service_id::VARCHAR IN (SELECT DISTINCT service_id FROM " . DBManager::ID_LABEL_MAPPINGS_TABLE . " ) ORDER BY service_name";

            $stmt = $pdo->query($sql);
            $rows = $stmt->fetchAll();
            ?>

            <em>Total Services found: <?= count($rows) ?></em>
            <table>
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
                                <input type="checkbox" value="<?= $row['base_service_id'] ?>">
                            </td>
                        </tr>

                    <?php endforeach; ?>


                </tbody>
            </table>

        </section>

    </main>

    <script>
        let service_id_arr = [];
        const service_id_elm = document.querySelector('input[name="service_ids"]');
        const fromDate = document.querySelector('#from_date');
        const toDate = document.querySelector('#to_date');

        document.querySelector('form').addEventListener('submit', function(event) {
            event.preventDefault();

            // Check if the dates are valid
            if (new Date(toDate.value) < new Date(fromDate.value)) {
                alert('Please select valid dates.');
                return;
            }

            if (service_id_arr.length === 0) {
                // no services selected
                alert('Please select atleast one service.');
                return;
            }

            service_id_elm.value = service_id_arr.join();

            // console.log(service_id_elm.value);

            // All ok, submit the form
            event.target.submit();

        });

        document.querySelectorAll('input[type="checkbox"]').forEach(function(checkbox, key) {
            checkbox.addEventListener('change', function(event) {
                // console.log(event.target);

                if (event.target.checked) {
                    // add service_id in the array
                    service_id_arr.push(event.target.value);
                } else {
                    // remove service_id from the array
                    service_id_arr = service_id_arr.filter(function(val, idx) {
                        return val != event.target.value
                    });
                }
            });
        });
    </script>
</body>

</html>