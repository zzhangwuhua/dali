<?php

namespace App\Service\View;

use App\Models\LisApply;
use App\Models\LisReport;
use App\Models\InterfaceLog;

class LisService implements BaseInterface
{

    public function handle($data)
    {
        if ($data['type'] == 'NW')
        {
            $result = $this->apply($data);
        }
        else
        {
            $result = $this->cancel($data);
        }
        return $result;
    }

    /**
     * 状态
     */
    public static function status()
    {
        $lis_apply_result = LisApply::select()
            ->where('exec_status_read', '=', 0)
            ->where('apply_type', '=', 'NW')
            ->whereIn('exec_status', [1, 2])
            ->get()
            ->toArray();

        if ($lis_apply_result)
        {
            $status = [];
            foreach ($lis_apply_result as $apply)
            {
                $status = [
                    'union_request_no' => $apply['union_request_no'],
                    'union_barcode' => '',
                    'apply_no' => '',
                    'status' => 'E',
                    'comments' => '',
                ];
                LisApply::where('id', $apply['id'])->update(['exec_status_read' => 1]);
                InterfaceLog::saveLog(microtime(), 'lis_status', $status);
                toBS('perform_status', $status);
            }
            
        }
    }

    /**
     * 报告
     */
    public static function report()
    {
        $reports = LisReport::select([
            'id',
            'union_request_no',
            'union_barcode',
            'examiner_id as union_examiner_id',
            'examiner_name as union_examiner_name',
            'examiner_time as union_examiner_time',
            'reviewer_id as union_reviewer_id',
            'reviewer_name as union_reviewer_name',
            'reviewer_time as union_reviewer_time',
            'item_code',
            'item_name',
            'item_check_result',
            'abnormal_flags as exception_tag',
            'item_unit',
            'item_ref_value',
        ])
            ->where('exec_status_read', '=', 0)
            ->where('reviewer_time', '>=', date('Y-m-d'))
            ->get()
            ->toArray();

        if ($reports)
        {
            $ids = array_column($reports, 'id');
            LisReport::whereIn('id', $ids)->update(['exec_status_read' => 1]);
            InterfaceLog::saveLog(microtime(), 'lis_report', $reports);
            toBS('lis_report', $reports);
        }
    }

    /**
     * 申请
     * @param $data
     * @return \Illuminate\Http\JsonResponse
     */
    private function apply($data)
    {
        logInfo('11111',$data,'Lis111');
        $insert_arr = [];
        $time = date('Y-m-d H:i:s', time());
        $data['list'] = json_decode($data['list'],true);
        $data['order_info'] = json_decode($data['order_info'],true);
        foreach ($data['list'] as $union)
        {
            $insert_arr[] = [
                'apply_no' => $data['order_info']['apply_no'],
                'patient_id' => $data['order_info']['medical_record_no'],
                'apply_type' => 'NW',
                'cust_name' => $data['order_info']['cust_name'],
                'cust_sex' => $data['order_info']['cust_sex_code'],
                'cust_id_card' => $data['order_info']['cust_id_card'],
                'cust_birthday' => $data['order_info']['cust_birthday'],
                'cust_type' => $data['order_info']['cust_card_type_code'],
                'cust_mobile' => $data['order_info']['cust_mobile'],
                'cust_marriage_code' => $data['order_info']['cust_marriage_code'],
                'exam_type' => $data['order_info']['order_cmp_name']?'1':'0',
                'union_request_no' => $union['union_request_no'],
                'union_code' => $union['union_code'],
                'union_name' => $union['his_union_name'],
                'union_fee' => $union['union_fee'],
                'disc_rate' => $union['disc_rate'],
                'union_pay_fee' => number_format($union['union_fee']*$union['disc_rate'],2),
                'union_barcode' => $union['union_barcode'],
                'tube_code' => $union['tube_code'],
                'tube_name' => $union['tube_name'],
                'sample_type_code' => $union['sample_type_code'],
                'sample_type_name' => $union['sample_type_name'],
                'dest_code' => $union['dest_code'],
                'dest_name' => $union['dest_name'],
                'applicant_id' => $data['order_info']['operator_id'],
                'applicant_name' => $data['order_info']['operator_name'],
                'apply_desc' => '',
                'apply_dept_code' => $union['exam_dept_code'],
                'apply_dept_name' => $union['exam_dept_name'],
                'exec_status' => 0,
                'exec_status_read' => 0,
                'create_time' => $time,
            ];
        }
        $result = LisApply::insert($insert_arr);
        return $result ? ['status' => true] : ['status' => false, 'msg' => '插入失败！'];
    }

    /**
     * 撤销
     * @param $data
     * @return \Illuminate\Http\JsonResponse
     */
    private function cancel($data)
    {
        $union_request_nos = array_column($data, 'union_request_no');

        // 检查是否有已执行的项目
        $exec_union = LisApply::select()->whereIn('union_request_no', $union_request_nos)
            ->where('apply_type', '=', 'NW')
            ->where('exec_status', '=', 1)
            ->get()
            ->toArray();

        if (count($exec_union) > 0)
        {
            $unions = implode(',', array_column($exec_union, 'union_name'));
            return ['status' => false, 'msg' => $unions . ' 已执行，不允许撤销'];
        }

        LisApply::whereIn('union_request_no', $union_request_nos)
            ->where('apply_type', '=', 'NW')
            ->where('exec_status', '=', 0)
            ->update([
                'apply_type' => 'CA',
                'cancel_time' => date('Y-m-d H:i:s'),
            ]);
        return ['status' => true,];
    }
}
