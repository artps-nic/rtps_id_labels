<?php
$sql = 'INSERT INTO ' . DBManager::ID_LABEL_MAPPINGS_TABLE . '(attrb_id, attrb_label, service_id, is_nested, service_type) VALUES(?, ?, ?, ?, ?)';
$stmt = $pdo->prepare($sql);

$sql2 = 'SELECT count(*) AS total FROM ' . DBManager::ID_LABEL_MAPPINGS_TABLE . ' WHERE attrb_id = ? AND service_id = ?';
$stmt2 = $pdo->prepare($sql2);

$sql3 = 'UPDATE ' . DBManager::ID_LABEL_MAPPINGS_TABLE . ' SET attrb_label = ?, is_nested = ?, service_type = ? WHERE attrb_id = ? AND service_id = ?';
$stmt3 = $pdo->prepare($sql3);


function read_attribute_file($file = '', $service_id = '', $service_type = 'RTPS')
{
    $output = [];

    if (empty($file) or empty($service_id) or empty($service_type) or filesize($file) <= 0) {
        $output['status'] = false;
        $output['msg'] = 'Invalid file';
        return $output;
    }

    $file_type = pathinfo($file, PATHINFO_EXTENSION);

    switch ($file_type) {
        case 'xls':

            if ($xls = SimpleXLS::parse($file)) {
                $rows = $xls->rows();
            } else {
                $output['status'] = false;
                $output['msg'] = SimpleXLS::parseError();
                return $output;
            }

            break;

        case 'xlsx':

            if ($xlsx = SimpleXLSX::parse($file)) {
                $rows = $xlsx->rows();
            } else {
                $output['status'] = false;
                $output['msg'] = SimpleXLSX::parseError();
                return $output;
            }
            break;

        case 'csv':
            $fh = fopen($file, 'r');
            $rows = array();

            while (!feof($fh)) {
                $csv_data = fgetcsv($fh);

                if (is_array($csv_data)) {
                    array_push($rows, $csv_data);
                }
            }

            fclose($fh);
            break;

        default:
            $output['status'] = false;
            $output['msg'] = 'Invalid attribute file';
            return $output;
    }


    $output['status'] = true;
    $output['msg'] = [];

    foreach ($rows as $i => $row) {

        if ($i === 0 or $i === 1) {
            continue;
            // 0th row => columns name
            // 1st row => empty
        }

        /** [0] =>S.No, [1] => Attribute ID, [2] => Attribute Label, [3] => Attribute Input Type. Remove:: (), / ~, :, ., ?  **/

        $attribute_ID = $row[1];
        $attribute_label = str_replace(' ', '_', preg_replace("/[\/\(\):'\.\?,]/", ' ', strtolower(trim($row[2], " \t\n\r\0\x0B:()?."))));

        // cut labels at 200 characters
        $attribute_label = explode("\n", wordwrap($attribute_label, 200))[0];

        // incase attribute_id / attribute_label is empty, ignore
        if (empty(trim($attribute_ID)) || empty($attribute_label)) {
            continue;
        }


        $is_nested = ($row[3] === 'fieldset') ? true : false;

        // Check if the ID alreay exists, then update the other fileds

        $GLOBALS['stmt2']->bindParam(1, $attribute_ID, PDO::PARAM_STR);
        $GLOBALS['stmt2']->bindParam(2, $service_id, PDO::PARAM_STR);
        $GLOBALS['stmt2']->execute();    // Returns TRUE | FALSE

        $row =  $GLOBALS['stmt2']->fetch();

        if ($row['total']) {
            // update
            $GLOBALS['stmt3']->bindParam(1, $attribute_label, PDO::PARAM_STR);
            $GLOBALS['stmt3']->bindParam(2, $is_nested, PDO::PARAM_BOOL);
            $GLOBALS['stmt3']->bindParam(3, $service_type, PDO::PARAM_STR);
            $GLOBALS['stmt3']->bindParam(4, $attribute_ID, PDO::PARAM_STR);
            $GLOBALS['stmt3']->bindParam(5, $service_id, PDO::PARAM_STR);

            if ($GLOBALS['stmt3']->execute()) {
                array_push($output['msg'], "attribute: $attribute_ID updated" . PHP_EOL);
            }
        } else {
            // insert
            $GLOBALS['stmt']->bindParam(1, $attribute_ID, PDO::PARAM_STR);
            $GLOBALS['stmt']->bindParam(2, $attribute_label, PDO::PARAM_STR);
            $GLOBALS['stmt']->bindParam(3, $service_id, PDO::PARAM_STR);
            $GLOBALS['stmt']->bindParam(4, $is_nested, PDO::PARAM_BOOL);
            $GLOBALS['stmt']->bindParam(5, $service_type, PDO::PARAM_STR);

            if ($GLOBALS['stmt']->execute()) {
                array_push($output['msg'], "attribute: $attribute_ID inserted" . PHP_EOL);
            }
        }
    }

    return $output;
}


function insert_id_labels($filepath = '', $service_id = '', $service_type = 'RTPS')
{
    $output = [];

    try {
        if (is_readable($filepath)) {

            $output = read_attribute_file($filepath, $service_id, $service_type);
        } else {
            $output['status'] = false;
            $output['msg'] = "Invalid filepath: $filepath";
        }
    } catch (\Exception $ex) {
        $output['status'] = false;
        $output['msg'] = $ex->getMessage();
    } finally {
        return $output;
    }
}
