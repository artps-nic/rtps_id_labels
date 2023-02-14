<?php
/* 
MAP External District Names TO RTPS District
*/

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
require_once './vendor/autoload.php';
$mongo = (new DBManager())->get_mongo_connection();

// Get all districts

if ($_SERVER['REQUEST_METHOD'] === 'GET') {

    $rtps_districts = $mongo->mis->districts_new->find(      // RTPS district list
        [],
        ['sort' => ['name' => 1], 'allowDiskUse' => true, 'noCursorTimeout' => true]
    )->toArray();

    $rtps_districts = array_map(function ($val) {

        return $val['name'];
    }, $rtps_districts);

    // MIS applications district list UNMAPPED
    $mis_districts = array_diff($mongo->mis->applications->distinct(
        'initiated_data.district',
        []
    ), $rtps_districts);
}

// Update Mappings
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['old_d_name']) && !empty($_POST['new_d_name'])) {

    $old_d_name = filter_input(INPUT_POST, 'old_d_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $new_d_name = filter_input(INPUT_POST, 'new_d_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    // var_dump($old_d_name, $new_d_name); die;

    $result = $mongo->mis->applications->updateMany(
        ['initiated_data.district' => $old_d_name],
        ['$set' => ['initiated_data.district' => $new_d_name]]
    );


    if ($result->getMatchedCount() || $result->getModifiedCount()) {

        $_SESSION['suc'] = $result->getModifiedCount() . ' application(s) updated.';
    } else {
        $_SESSION['error'] = 'Districts could not be updated';
    }

    // PRG
    header("Location: rtps_districts.php", TRUE, 303);
    exit(0);
} else {
    // POST Request with Invalid Data
    $_SESSION['error'] = 'Invalid Data for Updation!';
    // PRG
    header("Location: rtps_districts.php", TRUE, 303);
    exit(0);
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

    <style>
        select[name="filter"] {
            width: 15%;
            padding: 0.2em;
        }

        [type="submit"] {
            padding: 0.5em;
            background-color: rebeccapurple;
            color: whitesmoke;
            border: 0;
            text-transform: capitalize;
            border-radius: 0.3em;
        }

        [type="text"] {
            width: 50%;
            padding: 0.5em;
        }

        .info button {
            padding: 0.5em;
            background-color: whitesmoke;
            color: darkgreen;
            border: 1px solid darkgreen;
            text-transform: capitalize;
            border-radius: 0.3em;
            font-weight: bolder;
        }

        .cancel {
            padding: 0.7rem;
            border: none;
            background-color: lightpink;
            border-radius: 1rem;
            font-weight: bolder;
            outline: none;
        }

        .cancel:hover,
        .cancel:focus-within {
            outline: 2px solid darkmagenta;
        }
    </style>

</head>

<body>
    <nav>
        <a href="./welcome.php">HOME</a> |
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

        <section style="margin-top: 1em">

            <?php if (sizeof($mis_districts) === 0) : ?>

                <img style="display: block; width: 250px; margin-inline: auto; border: 1px solid black; border-radius: 1em;" src="./assets/relax.jpg" alt="relax" width="300">

                <h3 style="text-align: center; margin-top: 1em;">All the districts in MIS applications are Mapped. 🥳🙌</h3>

            <?php else : ?>

                <h3 style="margin-bottom: 1em">Unmapped External Districts Found: <?= sizeof($mis_districts) ?></h3>
                <table id="office-table">
                    <thead>
                        <tr>
                            <th>External District</th>
                            <th>RTPS District</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($mis_districts as $key => $m_val) : ?>

                            <tr>
                                <td style="width: 60%;"><?= $m_val ?? '' ?></td>
                                <td>
                                    <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST">

                                        <input type="hidden" name="old_d_name" value="<?= $m_val ?? '' ?>">

                                        <select name="new_d_name" required>
                                            <option value="" selected> --- please select a district to map --- </option>

                                            <?php foreach ($rtps_districts as $k => $r_val) : ?>
                                                <option value="<?= $r_val ?>"><?= $r_val ?></option>
                                            <?php endforeach; ?>


                                        </select>
                                        <input type="submit" value="Update">

                                    </form>

                                </td>
                            </tr>

                        <?php endforeach; ?>
                    </tbody>
                </table>

            <?php endif; ?>


        </section>


    </main>


    <script>
        // Show edit form
        document.querySelectorAll('.info > button').forEach(function(btn, key) {
            btn.addEventListener('click', function(event) {
                const div = btn.parentElement;
                const form = btn.parentElement.previousElementSibling;
                form.style.display = 'block';
                div.style.display = 'none';

            });
        });

        // Hide edit form
        document.querySelectorAll('.cancel').forEach(function(btn, key) {
            btn.addEventListener('click', function(event) {
                const form = btn.parentElement;
                const div = btn.parentElement.nextElementSibling;
                form.style.display = 'none';
                div.style.display = 'block';
            });
        });
    </script>

</body>

</html>