<?php
/**
 * his费用相关申请
 */
namespace App\Service;

use App\Models\HisFeeApply;
use App\Service\Common\BaseService;
use App\Service\Common\PlatformTrait;
use Illuminate\Http\JsonResponse;

class HisFeeService implements BaseService
{
    use PlatformTrait;
    const UserCode = '510561';

    // 总入口
    public function handle($params)
    {
        $list = json_decode($params['list'],true);
        $order_info = json_decode($params['order_info'],true);
        switch ($params['type']) {
                //新增申请
            case 'NW':
                return $this->sendFeeList($list,$order_info);
                //未缴费停用申请
            case 'ST':
                return $this->stopFee($list,$order_info);
                //退费申请
            case 'CA':
                return $this->refundApp($list,$order_info);
                //撤销回退费申请
            case 'STC':
                return $this->cancelRefundApp($list,$order_info);
            default:
                return fail('未知的申请类型！');
        }
    }

    /**
     * 新增医嘱
     * @return array
     */
    private function sendFeeList($list,$order_info)
    {
        $sendData = [];

        foreach ($list as $item) {
            $PE_InsuTypeCode = $item['union_pay_code'] == 1 ? '自费' : ($item['union_pay_code'] == 2 ? '健康体检' : '自费');
            $sendData[] = [
                'PE_RegNo' => $order_info['medical_record_no'],
                'PE_AdmNo' => $order_info['his_register_num'],
                'PE_OrdID' => $item['union_request_no'],
                'PE_ArcimCode' => $item['his_union_code'],
                'PE_DeptCode' => $order_info['apply_dept_code'],
                'PE_RecDeptCode' => $item['exe_dept_code'],
                'PE_UserCode' => self::UserCode,//$item['apply_doctor_code'],
                'PE_DocCode' => self::UserCode,//$item['apply_doctor_code'],
                'PE_InsuTypeCode' => $PE_InsuTypeCode,
                'PE_Qty' => $item['disc_rate'],
                'OrderFlag' => '',
                'PE_Price' => $item['union_fee'],
                'PE_TypeFlag' => $item['union_pay_code'] == 0 ? '0' : '1',
                'PE_SpecimenCode' => $item['sample_type_code'],
                'PE_OSVs' => 'Daishu',
            ];
        }

        if (!$sendData) {
            returnFail('参数不能为空！');
        }
        $data['OrdLists']['OrdInfo'] = $sendData;
        $sendXml =  arrayToXml($data,'Request');

        try {
            $res =  $this->requestHis($sendXml,'addFee');
            if ($res['ResultCode'] == 0) {
                //保存中间表
              //  $this->saveRequestInfo($list,$order_info,'NW');
                return success();
            } else {
                return fail($res['ResultContent']);
            }
        } catch (\Exception $e) {
            return fail($e->getMessage());
        }
    }

    /** 停止医嘱
     * @param $param
     * @return
     */
    private function stopFee($list,$order_info)
    {
        $sendData = [];
        foreach ($list as $item) {
            $sendData[] = [
                'PE_OrdRowID' => $item['fee_request_code'],
                'PE_OrdFlag'  => 'U'
            ];
        }

        $data = [
            'PE_UserCode' => self::UserCode,//$list[0]['apply_doctor_code'],
            'PE_Ords'     => ['PE_Ord' => $sendData],
        ];

        $sendXml =  arrayToXml($data,'Request');

        try {
            $res =  $this->requestHis($sendXml,'stopFee');
            if ($res['ResultCode'] == 0) {
                //保存中间表
               // $this->saveRequestInfo($list,$order_info,'ST');
                return success();
            } else {
                $resultContent =  strtr($res['ResultContent'],['<![CDATA[' => '',']]>' => '']);
                $resultContent =  xmlToArray($resultContent);
                return fail($resultContent['ResultContent']);
            }
        } catch (\Exception $e) {
            return fail($e->getMessage());
        }

    }

    /**
     * 退费申请
     * @param $param
     * @return
     */
    private function refundApp($list,$order_info)
    {
        $sendData = [];
        foreach ($list as $item) {
            $sendData = [
                'PE_PrtNo'    => $item['fee_no'],
                'PE_OrdRowID' => $item['fee_request_code'],
                'PE_UserCode' => self::UserCode,//$item['apply_doctor_code'],
                'PE_Count' => 1,
                'PE_Reason' => 2,
                'PE_Loc' => $order_info['apply_dept_code'],
            ];
        }

        $sendXml =  arrayToXml($sendData,'Request');

        try {
            $res =  $this->requestHis($sendXml,'refundApp');
            if ($res['ResultCode'] == 0) {
                //保存中间表
               // $this->saveRequestInfo($list,$order_info,'CA');
                return success();
            } else {
                $resultContent =  strtr($res['ResultContent'],['<![CDATA[' => '',']]>' => '']);
                $resultContent =  xmlToArray($resultContent);
                return fail($resultContent['ResultContent']);
            }
        } catch (\Exception $e) {
            return fail($e->getMessage());
        }

    }

    /**
     * 取消退费申请
     * @param $param
     * @return
     */
    private function cancelRefundApp($list,$order_info)
    {
        $sendData = [];
        foreach ($list as $item) {
            $sendData = [
                'PE_PrtNo'    => $item['fee_no'],
                'PE_UserCode' => self::UserCode,//$item['apply_doctor_code']
            ];
        }

        $sendXml =  arrayToXml($sendData,'Request');
        try {
            $res =  $this->requestHis($sendXml,'cancelRefundApp');
            if ($res['ResultCode'] == 0) {
                //保存中间表
               // $this->saveRequestInfo($list,$order_info,'STC');
                return success();
            } else {
                $resultContent =  strtr($res['ResultContent'],['<![CDATA[' => '',']]>' => '']);
                $resultContent =  xmlToArray($resultContent);
                return fail($resultContent['ResultContent']);
            }
        } catch (\Exception $e) {
            return fail($e->getMessage());
        }

    }

    private function saveRequestInfo($params,$order_info,$apply_type)
    {
        $insertData = [];
        if ($apply_type == 'NW') {
            foreach ($params as $item) {
                $insertData[] = [
                    'patient_id' => $order_info['medical_record_no'],
                    'apply_type' => $apply_type,
                    'cust_name' => $order_info['cust_name'],
                    'cust_id_card' => $order_info['cust_id_card'],
                    'order_code' => $order_info['order_code'],
                    'charge_id' => $order_info['charge_id'],
                    'exam_type' => $order_info['group_cust_id'] ? '1' : '0',
                    'his_union_code' => $item['his_union_code'],
                    'his_union_name' => $item['his_union_name'],
                    'his_item_fee' => isset($item['his_item_fee']) ? $item['his_item_fee'] * 100 : '',
                    'his_item_num' => $item['his_item_num'] ?? '',
                    'his_item_code' => $item['his_item_code'] ?? '',
                    'his_item_name' => $item['his_item_name'] ?? '',
                    'disc_rate' => $item['disc_rate'],
                    'pay_fee' => $item['disc_price'] * 100,
                    'union_request_no' => $item['union_request_no'],
                    'create_time' => date('Y-m-d H:i:s')
                ];
            }
            if ($insertData) {
                HisFeeApply::insert($insertData);
            }
        } else if($apply_type == 'ST') {
            HisFeeApply::where('union_request_no','=', $params[0]['order_code'])
                ->update([
                    'apply_type' => 'ST',
                    'cancel_time' => date('Y-m-d H:i:s'),
                ]);
        } else if ($apply_type == 'CA') {
            HisFeeApply::where('union_request_no','=', $params[0]['union_request_no'])
                ->update([
                    'apply_type' => 'CA',
                    'cancel_time' => date('Y-m-d H:i:s'),
                ]);
        } else if ($apply_type == 'STC') {
            HisFeeApply::where('union_request_no','=', $params[0]['union_request_no'])
                ->update([
                    'apply_type' => 'STC',
                    'cancel_time' => date('Y-m-d H:i:s'),
                ]);
        }

    }


}