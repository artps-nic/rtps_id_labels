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
$pdo = (new DBManager())->get_postgres_connection_eodb();
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
        <?php if (isset($_SESSION['suc'])) : ?>
            <p style="color:green;font-weight:bold;text-align:center;font-size:18px"><?= $_SESSION['suc'] ?></p>

            <?php unset($_SESSION['suc']); ?>

        <?php endif; ?>
        <?php if (isset($_SESSION['error'])) : ?>
            <p style="color:red;font-weight:bold;text-align:center;font-size:18px"><?= $_SESSION['error'] ?></p>

            <?php unset($_SESSION['error']); ?>

        <?php endif; ?>

        <div style="width:100%; display:flex">
            <div class="new_eodb_service_form" style="width:45%;margin:10px;">
                <h3 class="my-h3">Add New EODB Service</h3>
                <form action="./src/save_new_eodb.php" method="post">
                    <?php if (isset($_GET['id'])) {
                        $sql = "SELECT * FROM " . DBManager::EODB_SERVICE . " where base_service_id=$_GET[id]";
                        $stmt = $pdo->query($sql);
                        $rows = $stmt->fetch();
                    ?>
                        <table>
                            <tr>
                                <td width="20%">Service ID</td>
                                <td><input type="text" name="service_id" value="<?php echo $rows['base_service_id']; ?>" required pattern="[0-9]{4}"></td>
                            </tr>
                            <tr>
                                <td>Service Name</td>
                                <td><input type="text" name="service_name" required value="<?php echo $rows['service_name']; ?>"></td>
                            </tr>
                            <tr>
                                <td>Service Launch Date</td>
                                <td><input type="date" name="service_launch_date" value="<?php echo $rows['launch_date']; ?>" required></td>
                            </tr>
                            <tr>
                                <input type="hidden" name="ref_id" value="<?php echo $rows['base_service_id']; ?>" required>
                                <td colspan="2" style="text-align:center"><input type="submit" name="B2" value="UPDATE"></td>
                            </tr>
                        </table>
                    <?php } else { ?>
                        <table>
                            <tr>
                                <td width="20%">Service ID</td>
                                <td><input type="text" name="service_id" required pattern="[0-9]{4}"></td>
                            </tr>
                            <tr>
                                <td>Service Name</td>
                                <td><input type="text" name="service_name" required></td>
                            </tr>
                            <tr>
                                <td>Service Launch Date</td>
                                <td><input type="date" name="service_launch_date" required></td>
                            </tr>
                            <tr>
                                <td colspan="2" style="text-align:center"><input type="submit" name="B1" value="SAVE"></td>
                            </tr>
                        </table>
                    <?php } ?>
                </form>
            </div>
            <div style="width:55%;margin:10px;">
                <h3 class="my-h3">Existing EODB Services:</h3>

                <section>
                    <?php
                    $sql = "SELECT * FROM " . DBManager::EODB_SERVICE . " order by service_name";
                    $stmt = $pdo->query($sql);
                    $rows = $stmt->fetchAll();
                    ?>

                    <em>Total Services found: <?= count($rows) ?></em>
                    <table>
                        <thead>
                            <tr>
                                <th>Service ID</th>
                                <th>Service Name</th>
                                <th>Launch Date</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>

                            <?php foreach ($rows as $row) : ?>

                                <tr>
                                    <td><?= $row['base_service_id'] ?></td>
                                    <td><?= $row['service_name'] ?></td>
                                    <td><?= $row['launch_date'] ?></td>
                                    <td><a href="manage_eodb_services.php?id=<?= $row['base_service_id'] ?>" class="edit_btn">EDIT</a></td>
                                </tr>

                            <?php endforeach; ?>


                        </tbody>
                    </table>

                </section>
            </div>
        </div>


    </main>


</body>

</html>