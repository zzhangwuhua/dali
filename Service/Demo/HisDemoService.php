<?php
/**
 * 模拟HIS系统处理逻辑
 */
namespace App\Service\Demo;

use Illuminate\Support\Facades\DB;

class HisDemoService extends BaseDemoService
{


    public function hisService($param)
    {
        logInfo(print_r($param,true),[],'HISDemo_hisService');
        switch ($param->func) {
            case 'queryCustomer':
                return $this->queryCustomer($param->message);
            case 'createCustomer':
                return $this->createCustomer($param->message);
            case 'register':
                return $this->register($param->message);
            case 'addFee':
                return $this->addFee($param->message);
            case 'stopFee':
                return $this->stopFee($param->message);
            case 'refundApp':
                return $this->refundApp($param->message);
            case 'cancelRefundApp':
                return $this->cancelRefundApp($param->message);
            default:
                break;
        }
        $returnData = "<xml>
<ResultCode>-1</ResultCode>
<ResultContent>未知的申请类型</ResultContent>
</xml>";

        return ['result' => $returnData];

    }

    /**
     * 查询HIS患者信息
     * HIS查询数据库，查询不到则返回查询失败信息，有则返回患者信息，医院里标识唯一患者的号码，一般是患者ID或者门诊号
     * @param $params
     * @return string[]
     */
    public function queryCustomer($params)
    {
        logInfo('查询建档信息'.$params,[],'HISDemo_queryCustomer');
        /** 患者不存在 **/
        $returnData = "<xml>
<ResultCode>-1</ResultCode>
<ResultContent>患者信息没有找到</ResultContent>
</xml>";
        /** 返回已有患者ID  **/
      /*  $patientId = time();
        $returnData = "<xml>
<ResultCode>0</ResultCode>
<ResultContent></ResultContent>
<PatientId>{$patientId}</PatientId>
</xml>";*/

        return ['result' => $returnData];
    }

    /**
     * 申请HIS创建患者信息
     * 创建成功返回HIS患者唯一号码
     * @param $params
     * @return string[]
     */
    public function createCustomer($params)
    {
        logInfo('申请HIS创建患者信息'.$params,[],'HISDemo_createCustomer');

        $patientId = time();
        $returnData = "<xml>
<ResultCode>0</ResultCode>
<ResultContent></ResultContent>
<PatientId>{$patientId}</PatientId>
</xml>";
        return ['result' => $returnData];
    }

    /**
     * HIS登记挂号
     * 返回就诊流水号VisitId
     * @param $params
     * @return string[]
     */
    public function register($params)
    {
        logInfo('HIS登记挂号'.$params,[],'HISDemo_register');

        $visitId = time();
        $returnData = "<xml>
<ResultCode>0</ResultCode>
<ResultContent></ResultContent>
<Data>
<VisitId>{$visitId}</VisitId>
</Data>
</xml>";
        return ['result' => $returnData];
    }


    /**
     * 处理收费
     * @param $params
     * @return string[]
     */
    public function addFee($params)
    {
        logInfo('HIS收费'.$params,[],'HISDemo_addFee');

        $returnData = "<xml>
<ResultCode>0</ResultCode>
<ResultContent></ResultContent>
</xml>";
        return ['result' => $returnData];
    }

    /**
     * 处理删除费用
     *
     * @param $params
     * @return string[]
     */
    public function stopFee($params)
    {
        logInfo('HIS登记挂号'.$params,[],'HISDemo_stopFee');

        $visitId = time();
        $returnData = "<xml>
<ResultCode>0</ResultCode>
<ResultContent></ResultContent>
<Data>
<VisitId>{$visitId}</VisitId>
</Data>
</xml>";
        return ['result' => $returnData];
    }

    /**
     * 处理退费申请
     *
     * @param $params
     * @return string[]
     */
    public function refundApp($params)
    {
        logInfo('HIS登记挂号'.$params,[],'HISDemo_register');

        $visitId = time();
        $returnData = "<xml>
<ResultCode>0</ResultCode>
<ResultContent></ResultContent>
<Data>
<VisitId>{$visitId}</VisitId>
</Data>
</xml>";
        return ['result' => $returnData];
    }

    /**
     * 处理撤回退费申请
     *
     * @param $params
     * @return string[]
     */
    public function cancelRefundApp($params)
    {
        logInfo('HIS登记挂号'.$params,[],'HISDemo_cancelRefundApp');

        $visitId = time();
        $returnData = "<xml>
<ResultCode>0</ResultCode>
<ResultContent></ResultContent>
<Data>
<VisitId>{$visitId}</VisitId>
</Data>
</xml>";
        return ['result' => $returnData];
    }


    /**
     * 获取支付模拟数据，并模拟推送缴费状态信息体检
     * @param $order_code
     * @return
     */
    public function payFee($order_code)
    {
        $sql = "select ou.union_request_no,1 as fee_status from biz_order o 
    join biz_order_package_union opu on o.id = opu.order_id
    join intf_order_union ou on ou.order_pkg_union_id = opu.id and ou.order_id = opu.order_id
    where opu.order_union_option != -1 and opu.delete_flag = 0 and opu.status = 0
    and ou.delete_flag = 0 and ou.status = 0 
    and opu.pay_flag = 0 and opu.union_fee > 0
    and o.order_code = '{$order_code}'";

        $payFees = Db::connection('bs')
            ->select($sql);

        $consumeSql = "select opc.consume_request_no as union_request_no,1 as fee_status from biz_order o 
    join biz_order_package_consume opc on o.id = opc.order_id
    where opc.order_consume_option != -1 and opc.delete_flag = 0 and opc.status = 0
    and opc.pay_flag = 0 and opc.disc_price > 0 
    and o.order_code = '{$order_code}'";

        $payConsumeFees = Db::connection('bs')
            ->select($consumeSql);


        if ($payFees || $payConsumeFees) {
            $data = $payFees ? array_map('get_object_vars', $payFees) : [];
            $consumeData = $payConsumeFees ? array_map('get_object_vars', $payConsumeFees) : [];
            $list = array_merge($data,$consumeData);
          //  print_r($list);exit;
           // $payFees = json_decode($payFees,true);
            $xml = arrayToXml(['item' => isset($list[0]) ? $list : [$list] ],'xml');
            $rs = $this->requestIPEIS($xml,'FEE_STATUS');
            if ($rs['Body']['ResultCode'] == 1) {
                return success();
            }
        } else {
           return fail('查找不到缴费信息');
        }
    }

    /**
     * 模拟退费
     * @param $order_code
     * @return
     */
    public function refundFee($order_code)
    {
        $sql = "select ou.union_request_no,2 as fee_status from biz_order o 
    join biz_order_package_union opu on o.id = opu.order_id
    join intf_order_union ou on ou.order_pkg_union_id = opu.id and ou.order_id = opu.order_id
    where (opu.order_union_option = -1 or opu.delete_flag = 1) and opu.status = 0
    and ou.delete_flag = 0 and ou.status = 0 
    and opu.pay_flag = 1 and opu.union_fee > 0
    and o.order_code = '{$order_code}'";

        $payFees = Db::connection('bs')
            ->select($sql);

        $consumeSql = "select opc.consume_request_no as union_request_no,2 as fee_status from biz_order o 
    join biz_order_package_consume opc on o.id = opc.order_id
    where opc.order_consume_option != -1 and opc.delete_flag = 0 and opc.status = 0
    and opc.pay_flag = 0 and opc.disc_price > 0 
    and o.order_code = '{$order_code}'";

        $payConsumeFees = Db::connection('bs')
            ->select($consumeSql);

        if ($payFees || $payConsumeFees) {
            $data = $payFees ? array_map('get_object_vars', $payFees) : [];
            $consumeData = $payConsumeFees ? array_map('get_object_vars', $payConsumeFees) : [];
            $list = array_merge($data,$consumeData);

            $xml = arrayToXml(['item' => isset($list[0]) ? $list : [$list] ],'xml');
            $rs = $this->requestIPEIS($xml,'FEE_STATUS');
            if ($rs['Body']['ResultCode'] == 1) {
                return success();
            }
        } else {
            return fail('查找不到缴费信息');
        }
    }


}