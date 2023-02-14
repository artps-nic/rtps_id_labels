<?php
/* 
// Find corresponding Office form ServicePlus LGD
select DISTINCT parent_name3 from lgd.entity_details where org_code = 1234
and trim(parent_name3) != '' and parent_name3 not ilike '%Sub Registrar Office%'
and parent_name3 ~* '.*amg.*'
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

// Get all offices
$cursor = $mongo->mis->rtps_office_mappings->find([]);
$offices = $cursor->toArray();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $_SESSION['filter'] = (!empty($_GET['filter'])) ? trim($_GET['filter']) : ((empty($_SESSION['filter'])) ? 'ALL' : $_SESSION['filter']);

    if ($_SESSION['filter'] == 'MAPPED') {

        $offices = array_filter($offices, function ($value) {
            $doc = iterator_to_array($value);

            foreach ($doc as $key => $val) {
                if ($key != '_id' && $key != 'service_type' && !empty(trim($val))) {
                    return true;
                }
            }
        });
    } elseif ($_SESSION['filter'] == 'UNMAPPED') {
        $offices = array_filter($offices, function ($value) {
            $doc = iterator_to_array($value);

            foreach ($doc as $key => $val) {
                if ($key != '_id' && $key != 'service_type' && empty(trim($val))) {
                    return true;
                }
            }
        });
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['id']) &&  isset($_POST['new_name']) && !empty($_POST['old_name'])) {
    $result = $mongo->mis->rtps_office_mappings->updateOne(
        [
            '_id' => new MongoDB\BSON\ObjectId("{$_POST['id']}"),

        ],
        [
            '$set' => [
                "{$_POST['old_name']}" => $_POST['new_name'],
            ]
        ]
    );
    if ($result->getMatchedCount() || $result->getModifiedCount()) {
    
        $_SESSION['suc'] = $result->getModifiedCount() . ' offices updated';
        
        // Now update MIS applications
        $filter = ['initiated_data.submission_location' => $_POST['old_name']];
        switch ($_POST['service_type']) {
            case 'NOC':
            case 'BASUNDHARA':
                $filter['initiated_data.external_service_type'] = $_POST['service_type'];
                break;

            case 'GAD':
                $filter['initiated_data.department_id'] = '1469';
                break;

            default:
                # code...
                break;
        }

        $result = $mongo->mis->applications->updateMany(
            $filter,
            ['$set' => ['initiated_data.submission_location' => $_POST['new_name']]]
        );
        $_SESSION['suc'] .= (' and ' . $result->getModifiedCount() . ' applications updated.');
        
        
    } else {
        $_SESSION['error'] = 'Office could not be updated';
    }

    // PRG
    header("Location: office_mappings.php", TRUE, 303);
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
                <legend>Filter Offices</legend>

                <select name="filter" required>
                    <option value="ALL" <?= (($_SESSION['filter'] ?? 'ALL')  == 'ALL') ? 'selected' : ''  ?>>All</option>
                    <option value="MAPPED" <?= (($_SESSION['filter'] ?? '')  == 'MAPPED') ? 'selected' : '' ?>>Mapped</option>
                    <option value="UNMAPPED" <?= (($_SESSION['filter'] ?? '')  == 'UNMAPPED') ? 'selected' : '' ?>>Un-Mapped</option>
                </select>
                <input type="submit" value="Search">
            </fieldset>
        </form>

        <section style="margin-top: 1em">
            <em>Total Offices Found: <?= sizeof($offices) ?></em>
            <table id="office-table">
                <thead>
                    <tr>
                        <th>External Office Name</th>
                        <th>Service Type</th>
                        <th>RTPS Office Name</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($offices as $key => $value) :
                        foreach (iterator_to_array($value) as $k => $v) {
                            if ($k != '_id' && $k != 'service_type') {
                                $old_office = $k;
                                $new_office = $v;
                            }
                        }
                    ?>

                        <tr>
                            <td><?= $old_office ?? '' ?></td>
                            <td><?= $value['service_type'] ?></td>
                            <td>
                                <form action="<?= htmlspecialchars($_SERVER['PHP_SELF']) ?>" method="POST" style="display: none;">
                                    <input type="hidden" name="id" value="<?= strval($value['_id']) ?>">
                                    <input type="text" name="new_name" value="<?= $new_office ?? '' ?>">
                                    <input type="hidden" name="old_name" value="<?= $old_office ?? '' ?>">
                                    <input type="hidden" name="service_type" value="<?= $value['service_type'] ?>">
                                    <input type="submit" value="Update">

                                </form>
                                <div class="info">
                                    <?= $new_office ?? '' ?>
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
        document.querySelectorAll('.info > button').forEach(function(btn, key) {
            btn.addEventListener('click', function(event) {
                const div = btn.parentElement;
                const form = btn.parentElement.previousElementSibling;
                form.style.display = 'block';
                div.style.display = 'none';

            });
        });
    </script>

</body>

</html>