<?php
session_start([
    'name' => 'NIC_SESSION',
    'cookie_secure' => 1,
    'cookie_httponly' => 1,
    'cookie_samesite' => 'Lax'
]);
if (isset($_SESSION['user']) && $_SESSION['user']['username'] === 'rtps_admin') {
    // send back to the referer page
    $url = $_SERVER['HTTP_REFERER'] ?? './welcome.php';
    header("Location: {$url}", TRUE, 307);
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
    <nav class="main-nav login">
        <h3>RTPS DATA MANAGMENT PORTAL | NIC</h3>
    </nav>

    <main>
        <!-- FLASH Messages -->
        <?php if (isset($_SESSION['error'])) : ?>
            <p class="error"><?= $_SESSION['error'] ?></p>

            <?php unset($_SESSION['error']); ?>

        <?php endif; ?>

        <form action="./src/login.php" method="post" class="login">
            <fieldset>
                <legend>Please Log In</legend>
                <div>
                    <label for="username">Username:</label>
                    <input type="text" name="username" id="username" required autocomplete="off">
                </div>
                <div>
                    <label for="password">Password:</label>
                    <input type="password" name="password" id="password" required autocomplete="off">
                </div>
                <div>
                    <button type="submit">Login</button>
                </div>
            </fieldset>
        </form>

    </main>
</body>

</html>