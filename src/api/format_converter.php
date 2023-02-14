<?php
function convert_data($ini_json = null, $exe_json = null, $base_service_id = '')
{
    $initiated_data = json_decode($ini_json, true);
    $exec_data = json_decode($exe_json, true);

    $execution_data = array();

    if (is_array($initiated_data)) {
        // service_id, appl_id, base_service_id, department_id :: to string

        $initiated_data['appl_info']['service_id'] = strval($initiated_data['appl_info']['service_id']);
        $initiated_data['appl_info']['appl_id'] = strval($initiated_data['appl_info']['appl_id']);
        $initiated_data['appl_info']['base_service_id'] = strval($initiated_data['appl_info']['base_service_id']);
        $initiated_data['appl_info']['department_id'] = strval($initiated_data['appl_info']['department_id']);

        // add enclosure_details and attribute_details

        $initiated_data['appl_info']['enclosure_details'] = $initiated_data['enclosure_data'];
        $initiated_data['appl_info']['attribute_details'] = $initiated_data['application_form_attributes'];

        // Adding submission_data as string
        $initiated_data['appl_info']['submission_date_str'] = explode(' ', $initiated_data['appl_info']['submission_date'])[0];


        // Adding pending/deliverd/reject IN/BEYOND time
        $initiated_data['appl_info']['pit'] = 0;                  // pending
        $initiated_data['appl_info']['pbt'] = 0;

        $initiated_data['appl_info']['dit'] = 0;                  // delivered
        $initiated_data['appl_info']['dbt'] = 0;

        $initiated_data['appl_info']['rit'] = 0;                  // rejected
        $initiated_data['appl_info']['rbt'] = 0;


        // For IWT service, add new department
        /*   if (in_array($base_service_id, IWT_SERVICES)) {
            // actual dept info
            $initiated_data['appl_info']['actual_dept_id'] = $initiated_data['appl_info']['department_id'];
            $initiated_data['appl_info']['actual_dept_name'] = $initiated_data['appl_info']['department_name'];

            // modified dept info
            $initiated_data['appl_info']['department_id'] = '9999';
            $initiated_data['appl_info']['department_name'] = 'Directorate of Inland Water Transport';
        } */
    }

    if (is_array($exec_data)) {

        foreach ($exec_data as $exec) {

            // Check for null values
            if (is_null($exec)) {
                continue;
            }

            $exec_data_actual = merge_arrys($exec);

            array_push($execution_data, array(
                'task_details' => $exec_data_actual['task_info'],
                'official_form_details' => $exec_data_actual['official_form_attributes'],
                'applicant_task_details' => $exec_data_actual['applicant_task_data'],
            ));
        }
    }

    /*  Sort execution data array in DESC. 
        Latest exe_data must be at TOP.
    */

    usort($execution_data, function ($a, $b) {
        $time1 = strtotime($a['task_details']['executed_time']);
        $time2 = strtotime($b['task_details']['executed_time']);

        if ($time1 == $time2) {
            return 0;
        }

        return ($time1 < $time2) ? 1 : -1;
    });


    // Adding latest execution_date as string in initiated_data

    if (is_array($initiated_data)) {
        $initiated_data['appl_info']['execution_date_str'] = $execution_data[0]['task_details']['executed_time'] ?? $initiated_data['appl_info']['submission_date'];

        $initiated_data['appl_info']['execution_date_str'] = explode(' ', $initiated_data['appl_info']['execution_date_str'])[0];
    }


    $history_data = array(
        'initiated_data' => $initiated_data['appl_info'] ?? null,
        'execution_data' => $execution_data,
    );

    return $history_data;
}

/* Merge nested exec_data into one array  */

function merge_arrys($arr)
{

    if (count($arr) > 1) {
        // base case
        return $arr;
    }

    return array_merge([], merge_arrys(reset($arr)));
}


function clean_data($data)
{
    if (strpos($data, '~') !== false) {

        //  ~ found in the string

        $arr = explode('~', $data);
        return end($arr);
    }

    return $data;
}

// ServicePlus Form Actions
const ACTIONS_IDS = array(
    '8' => 'Recommend',
    '9' => 'Forward',
    '10' => 'Reject',
    '11' => 'Deliver',
    '12' => 'Partial Deliver',
    '20' => 'Not Recommend',
    '22' => 'Partial Reject',
    '23' => 'Hold',
    '30' => 'Re-Appeal',
    '34' => 'Return to Edit Application',
    '50' => 'Transfered',
);


// Adding cerificates path
function add_certs(&$appl, $pdo)
{
    $query = "select file_name, application_cert_flag, application_current_process_id from schm_sp.application_cert where application_id = {$appl['initiated_data']['appl_id']} and file_name is not null and length(trim(file_name)) > 0 order by application_current_process_id asc";
    $stmt = $pdo->query($query);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $appl['initiated_data']['certs'] = $rows;
}
