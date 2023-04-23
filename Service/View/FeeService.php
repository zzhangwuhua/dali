<?php

namespace App\Service\View;

use App\Models\HisFeeApply;
use App\Models\HisFeeStatus;

class FeeService implements BaseInterface
{

    public function handle($data)
    {
        $items = json_decode($data['items'], true);

        if ($data['apply_type'] == 'NW')
        {
            $common_arr = [
                'patient_id' => $data['medical_record_no'],
                'order_code' => $data['order_code'],
                'cust_name' => $data['cust_name'],
                'cust_mobile' => '',
                'exam_type' => $data['pay_code'] == '1' ? '1' : '2',
                'operator_code' => $data['operator_id'],
                'operator_name' => $data['operator_name'],
                'create_time' => date('Y-m-d H:i:s', time()),
            ];

            $insert_arr = [];
            foreach ($items as $item)
            {
                $insert_arr[] = array_merge($common_arr, [
                    'fee_type' => 0,
                    'fee_request_code' => $item['fee_request_code'],
                    'pay_total' => $item['his_item_price'],
                    'his_union_code' => $item['his_union_code'],
                    'his_union_name' => $item['his_union_name'],
                    'his_item_code' => $item['his_item_code'],
                    'his_item_name' => $item['his_item_name'],
                    'his_item_fee' => $item['his_item_price'],
                    'disc_rate' => $item['disc_rate'],
                    'pay_fee' => $item['disc_price'],
                    'his_item_num' => $item['his_fee_num'],
                    'union_request_no' => $item['union_request_no'],
                    'exe_dept_code' => $item['exe_dept_code'],
                    'exe_dept_name' => $item['exe_dept_name'],
                    'exec_status_read' => 0,
                ]);
            }

            HisFeeApply::insert($insert_arr);
        }
        else
        {
            $union_request_nos = array_column($items, 'union_request_no');
            HisFeeApply::whereIn('union_request_no', $union_request_nos)->update(['fee_type' => 1, 'exec_status_read' => 0, 'update_time' => date('Y-m-d H:i:s', time())]);
        }

        return returnSuccess();
    }

    public static function status()
    {
        $result = HisFeeStatus::select()
            ->whereIn('fee_status', [1, 2])
            ->where('exec_status_read', 0)
            ->get()
            ->toArray();

        if ($result)
        {
            $updates_ids = [];
            $fee_request_code = [];
            $fee_arr = [];
            foreach ($result as $union)
            {
                $updates_ids[] = $union['id'];
                $fee_request_code[] = $union['fee_request_code'];
                $fee_arr[] = [
                    'fee_status' => $union['fee_status'],
                    'order_code' => $union['order_code'],
                    'union_pay_time' => $union['pay_time'],
                    'fee_no' => $union['fee_no'],
                    'operator_code' => $union['fee_operator_code'],
                    'operator_name' => $union['fee_operator_name'],
                    'fee_request_code' => $union['fee_request_code'],
                ];
            }
            
            try {
                toBS('check_fee_status', $fee_arr);
                HisFeeStatus::whereIn('id', $updates_ids)->update(['exec_status_read' => 1]);
                HisFeeApply::whereIn('fee_request_code', $fee_request_code)->update(['exec_status_read' => 1]);
            }catch(\Exception $e) {
                logInfo('缴费申请 post -> ', $e->getMessage(), 'sendFeeData');
            }
        }
    }
}
