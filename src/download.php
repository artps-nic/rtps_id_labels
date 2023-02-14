<?php
session_start([
    'name' => 'NIC_SESSION',
    'cookie_secure' => 1,
    'cookie_httponly' => 1,
    'cookie_samesite' => 'Lax'
]);
if (!isset($_SESSION['user'])) {
    header("Location: ../index.php", TRUE, 307);
    exit(1);
}

require_once '../vendor/autoload.php';
require_once './db_con.php';

$pdo = (new DBManager())->get_postgres_connection();

$service_id = filter_input(INPUT_GET, 'service-id', FILTER_SANITIZE_NUMBER_INT);

if (!empty($service_id)) {
    // input validation
    $service_id = trim($service_id);
    $service_id = filter_var($service_id, FILTER_VALIDATE_INT);

    if ($service_id !== false) {
        // All ok

        $table = DBManager::ATTRIBUTE_MAST_TABLE;

        $sql = <<<EOD
        SELECT row_position AS s_no, attribute_id, attribute_label, attribute_input_type
        from {$table}
        WHERE LEFT(service_id::VARCHAR, 4) = ?
        and attribute_input_type not in ('label', 'button', 'declareText', 'blank')
        and attribute_label != ' '
        EOD;

        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(1, $service_id, PDO::PARAM_STR);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        if (empty($rows)) {
            redirect_with_message("Attribute list not found for Service #{$service_id}");
        }
        
        
        // Get attribute lists from ID_LABEL_MAPPINGS table, if exists
        $stmt = $pdo->prepare('select * from ' .  DBManager::ID_LABEL_MAPPINGS_TABLE . ' where service_id = ?');
        $stmt->bindParam(1, $service_id, PDO::PARAM_STR);
        $stmt->execute();
        $rows_old = $stmt->fetchAll();

        if (!empty($rows_old)) {
            // Replace attribute labels
            foreach ($rows as &$i) {                            // Pass by reference
                foreach ($rows_old as &$j) {

                    if ($i['attribute_id'] == $j['attrb_id']) {
                        $i['attribute_label'] = $j['attrb_label'];
                    }
                }
            }
        }

        download_attribute_list($rows, $service_id);
    } else {
        redirect_with_message('Invalid Service ID');
    }
} else {
    // Invalid input
    redirect_with_message('Invalid Service ID');
}


function redirect_with_message($error = '')
{
    $_SESSION['error'] = $error;
    header("Location: ../index.php", TRUE, 307);
    exit(1);
}

function download_attribute_list($rows = [], $service_id)
{
    // export data as csv/excel

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->getColumnDimension('A')->setAutoSize(true);
    $sheet->getColumnDimension('B')->setAutoSize(true);
    $sheet->getColumnDimension('C')->setAutoSize(true);
    $sheet->getColumnDimension('D')->setAutoSize(true);

    $sheet->setCellValue('A1', 'S No.');
    $sheet->setCellValue('B1', 'Attribute ID');
    $sheet->setCellValue('C1', 'Attribute label');
    $sheet->setCellValue('D1', 'Attribute input type');

    $sheet->getStyle('A1:D1')->applyFromArray([
        'font' => [
            'bold' => true,
        ],
        'alignment' => [
            'horizontal' => \PhpOffice\PhpSpreadsheet\Style\Alignment::HORIZONTAL_LEFT,
        ],
    ]);

    $cell_no = 3;
    foreach ($rows as $row) {
        $sheet->setCellValue("A{$cell_no}", $row['s_no']);
        $sheet->setCellValue("B{$cell_no}", $row['attribute_id']);
        $sheet->setCellValue("C{$cell_no}", $row['attribute_label']);
        $sheet->setCellValue("D{$cell_no}", $row['attribute_input_type']);

        $cell_no++;
    }

    // redirect output to client browser
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');

    header(<<<EOD
    Content-Disposition: attachment;filename="attribute_list_{$service_id}.xlsx"
    EOD);

    header('Cache-Control: max-age=0');

    $writer = \PhpOffice\PhpSpreadsheet\IOFactory::createWriter($spreadsheet, 'Xlsx');
    $writer->save('php://output');
}
