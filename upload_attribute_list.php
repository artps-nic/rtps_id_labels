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

        <?php if (isset($_SESSION['error'])) : ?>
            <p class="error"><?= $_SESSION['error'] ?></p>

            <?php unset($_SESSION['error']); ?>

        <?php endif; ?>

        <p class="note">N.B. Maximum file size is 3MB. Supported file types are .csv, .xls and .xlsx.</p>

        <form action="./src/upload.php" method="POST" enctype="multipart/form-data">
            <label for="service_id">
                <strong>Enter Service ID:</strong>
            </label>
            <input type="number" name="service_id" id="service_id" required autocomplete="off">
            <br>

            <!-- Service Type -->
            <label>
                <strong>Choose Service Type:</strong>
            </label>
            <label for="rtps">
                <input type="radio" name="service_type" id="rtps" value="RTPS" checked> RTPS
            </label>
            <label for="eodb">
                <input type="radio" name="service_type" value="EODB" id="eodb"> EODB
            </label>
            <br>

            <label for="attribute_file">
                <strong>Select attribute file:</strong>
            </label>
            <input type="file" name="attribute_file" id="attribute_file" accept=".csv, .xls, .xlsx" required>

            <br>
            <br>
            <button type="submit">Submit</button>
            <button type="reset">Reset</button>

        </form>

        <section></section>

    </main>
</body>

</html>