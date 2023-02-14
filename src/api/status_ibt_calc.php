<?php
function calc_appl_status_time(&$appl)     // call be reference
{
    if (is_array($appl['initiated_data'])) {

        $sd = $appl['initiated_data']['submission_date_str'];
        $ed = $appl['initiated_data']['execution_date_str'];
        $tl = $appl['initiated_data']['service_timeline'];

        switch ($appl['initiated_data']['appl_status']) {
            case 'D':
                // deliver
                if (in_byond_time_check($sd, $ed, $tl)) {
                    $appl['initiated_data']['dit'] = 1;
                } else {
                    $appl['initiated_data']['dbt'] = 1;
                }

                break;

            case 'R':
                // reject
                if (in_byond_time_check($sd, $ed, $tl)) {
                    $appl['initiated_data']['rit'] = 1;
                } else {
                    $appl['initiated_data']['rbt'] = 1;
                }

                break;

            default:
                // pending
                if (in_byond_time_check($sd, date('d-m-Y'), $tl)) {
                    $appl['initiated_data']['pit'] = 1;
                } else {
                    $appl['initiated_data']['pbt'] = 1;
                }

                break;
        }
    }
}


function in_byond_time_check($sd_str = '', $ed_str = '', $tl = 0)
{
    $exp_date = (DateTime::createFromFormat('d-m-Y', $sd_str))->add(new DateInterval("P{$tl}D"));
    $exec_date = DateTime::createFromFormat('d-m-Y', $ed_str);

    return $exec_date <= $exp_date;
}
