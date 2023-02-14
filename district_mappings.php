<?php
/* 
MAP Officenames to District for those offices that don't have 
submission_location TO district mapping in LGD tables
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

// Get all offices+districts

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $_SESSION['filter'] = (!empty($_GET['filter'])) ? trim($_GET['filter']) : ((empty($_SESSION['filter'])) ? 'ALL' : $_SESSION['filter']);

    // $districts = ($mongo->mis->office_district_mappings->findOne([
    //     'office_name' => 'META_DIST_LIST'
    // ]))['district'] ?? [];

    $districts = $mongo->mis->districts_new->find(
        [],
        ['sort' => ['name' => 1], 'allowDiskUse' => true, 'noCursorTimeout' => true]
    )->toArray();

    // var_dump($districts);die;

    if ($_SESSION['filter'] == 'MAPPED') {

        $cursor = $mongo->mis->office_district_mappings->find([
            '$expr' => [
                '$and' => [
                    ['$ne' => ['$office_name', 'META_DIST_LIST']],
                    ['$gt' => [
                        ['$strLenCP' => ['$trim' => ['input' =>  '$district']]],
                        0
                    ]]
                ]
            ]
        ]);
    } elseif ($_SESSION['filter'] == 'UNMAPPED') {

        $cursor = $mongo->mis->office_district_mappings->find([
            '$expr' => [
                '$and' => [
                    ['$ne' => ['$office_name', 'META_DIST_LIST']],
                    ['$eq' => [
                        ['$strLenCP' => ['$trim' => ['input' =>  '$district']]],
                        0
                    ]]
                ]
            ]
        ]);
    } else {
        $cursor = $mongo->mis->office_district_mappings->find([
            'office_name' => ['$ne' => 'META_DIST_LIST']
        ]);
    }

    $offices = $cursor->toArray();

    // var_dump($offices); die;
}
// Update Mappings
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['id']) && !empty($_POST['office_name']) && !empty($_POST['new_d_name']) && isset($_POST['old_d_name'])) {

    //1. First update the Mappings Collection
    //2. Then update the applications 
    // var_dump($_POST['id'], $_POST['office_name'], $_POST['new_d_name'], $_POST['old_d_name']); die;

    $office_name = filter_input(INPUT_POST, 'office_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
    $new_district = filter_input(INPUT_POST, 'new_d_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

    $result = $mongo->mis->office_district_mappings->updateOne(
        [
            '_id' => new MongoDB\BSON\ObjectId("{$_POST['id']}"),
            'office_name' =>  $office_name,
        ],
        [
            '$set' => [
                'district' => $new_district,
            ]
        ]
    );
    if ($result->getMatchedCount() || $result->getModifiedCount()) {
        $_SESSION['suc'] = $result->getModifiedCount() . ' districts updated';

        // Now update MIS applications

        $result = $mongo->mis->applications->updateMany(
            ['initiated_data.submission_location' => $office_name],
            ['$set' => ['initiated_data.district' => $new_district]]
        );
        $_SESSION['suc'] .= (' and ' . $result->getModifiedCount() . ' applications updated.');
    } else {
        $_SESSION['error'] = 'Districts could not be updated';
    }

    // PRG
    header("Location: district_mappings.php", TRUE, 303);
    exit(0);
} else {
    // POST Request with Invalid Data
    $_SESSION['error'] = 'Invalid Data for Updation!';
    // PRG
    header("Location: district_mappings.php", TRUE, 303);
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

        <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="GET">
            <fieldset>
                <legend>Filter Office/District Mappings</legend>

                <select name="filter" required>
                    <option value="ALL" <?= (($_SESSION['filter'] ?? 'ALL')  == 'ALL') ? 'selected' : ''  ?>>All</option>
                    <option value="MAPPED" <?= (($_SESSION['filter'] ?? '')  == 'MAPPED') ? 'selected' : '' ?>>Mapped</option>
                    <option value="UNMAPPED" <?= (($_SESSION['filter'] ?? '')  == 'UNMAPPED') ? 'selected' : '' ?>>Un-Mapped</option>
                </select>
                <input type="submit" value="Search">
            </fieldset>
        </form>

        <section style="margin-top: 1em">
            <em>Total Records Found: <?= sizeof($offices) ?></em>
            <table id="office-table">
                <thead>
                    <tr>
                        <th>RTPS Office Name</th>
                        <th>District Name</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($offices as $key => $value) : ?>

                        <tr>
                            <td style="width: 60%;"><?= $value['office_name'] ?? '' ?></td>
                            <td>
                                <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST" style="display: none;">
                                    <input type="hidden" name="id" value="<?= strval($value['_id']) ?>">
                                    <input type="hidden" name="office_name" value="<?= $value['office_name'] ?? '' ?>">
                                    <input type="hidden" name="old_d_name" value="<?= $value['district'] ?? '' ?>">
                                    <select name="new_d_name">
                                        <option value="" selected>--- please select a district ---</option>

                                        <?php foreach ($districts as $d => $dv) : ?>
                                            <option value="<?= $dv['_id'] ?>"><?= $dv['name'] ?></option>
                                        <?php endforeach; ?>


                                    </select>
                                    <input type="submit" value="Update">
                                    <button type="button" class="cancel">X</button>

                                </form>
                                <div class="info">
                                    <?= $value['district'] ?? '' ?>
                                    <button>Edit</button>
                                </div>
                            </td>
                        </tr>

                    <?php endforeach; ?>
                </tbody>
            </table>


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