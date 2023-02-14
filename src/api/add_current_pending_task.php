<?php

function add_current_pending_task($pdo, &$document, $mongo)     // Pass by Reference
{
    $task_info = [
        'task_details' => [],
        'official_form_details' => []
    ];

    // Get the last task_id 
    $stmt = $pdo->query("select *, initiated_on::timestamp from schm_sp.spe_service_application_current_process where spdi_application_id = " . intval($document['initiated_data']['appl_id']) .  " order by spdi_application_current_process_id desc limit 1");
    $current_task = $stmt->fetch();

    if (empty($current_task)) {
        // Latest task is empty
        return;
    }

    $task_info['task_details']['appl_id'] = $document['initiated_data']['appl_id'];
    $task_info['task_details']['task_id'] = $current_task['spdi_next_workflow_level'];
    $task_info['task_details']['current_process_id'] = $current_task['spdi_application_current_process_id'];
    $task_info['task_details']['task_name'] = $current_task['spdv_next_process_flow_task_name'];
    $task_info['task_details']['action_taken'] = $current_task['spdv_action_taken'];

    $task_info['task_details']['executed_time'] = null;

    // Convert it into mongodb date
    $task_info['task_details']['received_time'] = $current_task['initiated_on'];


    // Check pull user_id
    if (!empty($current_task['spdi_application_pull_userid'])) {

        $task_info['task_details']['user_type'] = check_user_type($pdo, $current_task['spdi_application_pull_userid']);

        if ($task_info['task_details']['user_type'] == 'offical') {
            $document['initiated_data']['applicant_query'] = false;

            //    Get user info
            $task_info['task_details']['user_detail'][] = get_user_info($pdo, $current_task['spdi_application_pull_userid'], $mongo);
        } else {
            $document['initiated_data']['applicant_query'] = true;
        }
    } else {
        // Check user mappings associated with that task

        $stmt = $pdo->query("select * from schm_sp.application_current_process_user where spdi_application_current_process_id = {$current_task['spdi_application_current_process_id']}");
        $users = $stmt->fetchAll();

        if (empty($users)) {
            //  Timer task/Applicant Query task/Application for Edit/Gateway Task

            $task_info['task_details']['user_type'] = 'offical';

            $document['initiated_data']['applicant_query'] = false;

            //  Check for applicant_task
            $stmt = $pdo->query("select * from schm_sp.applicant_task where node_id = {$current_task['spdi_next_workflow_level']}");
            $row = $stmt->fetch();

            if (!empty($row)) {
                // Applicant task
                $task_info['task_details']['user_type'] = 'applicant';

                $document['initiated_data']['applicant_query'] = true;
            }
        } else {

            foreach ($users as $key => $user) {

                $task_info['task_details']['user_type'] = check_user_type($pdo, $user['spdi_create_user_id']);

                if ($task_info['task_details']['user_type'] == 'offical') {
                    $document['initiated_data']['applicant_query'] = false;

                    //    Get user info
                    $task_info['task_details']['user_detail'][] = get_user_info($pdo, $user['spdi_create_user_id'], $mongo);
                } else {
                    $document['initiated_data']['applicant_query'] = true;
                    $task_info['task_details']['user_detail'] = null;
                }
            }
        }
    }


    // Only add task_node which is yet to be performed 
    // if ($current_task['spdv_action_taken'] == 'N') {
    //     array_unshift($document['execution_data'], $task_info);
    // }

    array_unshift($document['execution_data'], $task_info);
}


function get_user_info($pdo, $userid, $mongo)
{
    $stmt = $pdo->query("select * from schm_sp.m_adm_sign where user_id = {$userid}");
    $user = $stmt->fetch();

    if (empty($user)) {
        return null;
    }

    $data['user_id'] = $user['user_id'];
    $data['user_name'] = $user['user_name'];
    $data['sign_no'] = $user['sign_no'];
    $data['mobile_no'] = $user['mobile_no'];
    $data['location_id'] = $user['location_id'];
    $data['location_name'] = trim(preg_replace('/(- Line)$/iu', '', $user['spdv_location_name']));

    // Add district info

    // $data['district'] = add_district_for_curr_location($data['location_name'], $pdo, $mongo);


    // Get user designation
    $stmt = $pdo->query("select * from schm_sp.spe_create_user where spdi_create_user_id = {$user['user_id']}");
    $row = $stmt->fetch();

    if (!empty($row)) {
        $data['email'] = $row['spdv_user_email_id'];
        $data['designation'] = $row['spdv_designation_name'];
        $data['department_name'] = $row['spdv_department_name'];
    }

    return $data;
}


function check_user_type($pdo, $userid)    // Applicant or Official
{
    $stmt = $pdo->query("select * from schm_sp.m_adm_sign where user_id = {$userid}");
    $user = $stmt->fetch();

    if ($user['sign_role'] == 'CTZN') {
        return 'applicant';
    }

    return 'offical';
}
