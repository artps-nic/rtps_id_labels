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
        <a href="./upload_attribute_list.php">Upload Another File</a> |
        <a href="./src/logout.php" title="Logout">
            <img src="./assets/logout2.png" alt="Logout" width="32">
        </a>
    </nav>

    <?php if (isset($_SESSION['data'])) : ?>

        <main>
            <?php if ($_SESSION['data']['status']) : ?>
                <h3 class="status success">File Uploaded and Executed Successfully!</h3>

            <?php else : ?>
                <h3 class="status error">Error occured while executing the file!</h3>

            <?php endif; ?>

            <hr>

            <section class="output">
                <p>Output:</p>

                <div>
                    <?php if (is_array($_SESSION['data']['msg'])) : ?>
                        <?php foreach ($_SESSION['data']['msg'] as $text) : ?>
                            <?= $text ?>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <?= $_SESSION['data']['msg'] ?>
                    <?php endif; ?>
                </div>

            </section>

        </main>

    <?php
        unset($_SESSION['data']);
    endif;
    ?>

</body>

</html>