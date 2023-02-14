<?php
require_once 'vendor/autoload.php';

$collection = (new \MongoDB\Client('mongodb://10.194.74.94:27017'))->portal->services;

try {
    $cursor = $collection->aggregate(
        [
            ['$match' => ['online' => true]],
            ['$lookup' => ['from' => 'departments', 'localField' => 'department_id', 'foreignField' => 'department_id', 'as' => 'dept_docs']],
            ['$unwind' => '$dept_docs'],
            ['$project' => ['_id' => 0, 'department' => '$dept_docs.department_name.en', 'service' => '$service_name.en']],
            ['$group' =>  ['_id' => '$department', 'Department' => ['$first' => '$department'], 'Services' => ['$addToSet' => '$service']]],
            ['$unset' => '_id'],
            ['$sort' => ['Department' => 1]],
        ],
        ['allowDiskUse' => true, 'noCursorTimeout' => true]
    );
    $docs_arr = $cursor->toArray();
    // var_dump($docs_arr);

    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->fromArray(['DEPARTMENTS', 'SERVICES'], null, 'A1');

    for ($i = 0, $r = 2; $i < sizeof($docs_arr); $i++) {
        $c = 1;
        // Write deptt. names
        $sheet->setCellValueByColumnAndRow($c, $r, ucwords($docs_arr[$i]['Department']));

        // Write service names
        for ($j = 0, $c++; $j < sizeof($docs_arr[$i]['Services']); $j++, $r++) {
            $sheet->setCellValueByColumnAndRow($c, $r, ucwords($docs_arr[$i]['Services'][$j]));
        }
    }

    $sheet->getColumnDimension('A')->setAutoSize(true);
    $sheet->getColumnDimension('B')->setAutoSize(true);
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
    $writer->save('rtps_services.xlsx');

    echo  'Done' . PHP_EOL;
} catch (\Exception $ex) {
    echo $ex->getMessage() . PHP_EOL;
}
