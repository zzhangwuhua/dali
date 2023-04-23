<?php
/**
 * 模拟LIS系统处理逻辑
 */
namespace App\Service\Demo;

use Illuminate\Support\Facades\DB;

class LisDemoService extends BaseDemoService
{


    public function lisService($params)
    {
        switch ($params->func) {
            case 'sendLisApply':
                return $this->sendLisApply($params->message);
            case 'cancelLisApply':
                return $this->cancelLisApply($params->message);
            default:
                break;
        }

        $returnData = "<xml>
<return_code>FAIL</return_code>
<return_msg>未知的申请类型</return_msg>
</xml>";

        return ['result' => $returnData];

    }
    /**
     * 接收体检LIS新增申请
     *
     * @param $params
     * @return string[]
     */
    public function sendLisApply($params)
    {
        logInfo('接收参数'.$params,[],'LISDemo_sendLisApply');
        /** 返回处理成功信息 **/
        $returnData = "<xml>
<return_code>SUCCESS</return_code>
<return_msg></return_msg>
</xml>";

        return ['result' => $returnData];
    }

    /**
     * 接收体检LIS撤销申请
     *
     * @param $params
     * @return string[]
     */
    public function cancelLisApply($params)
    {
        logInfo('接收参数'.$params,[],'LISDemo_cancelLisApply');
        /** 返回处理成功信息 **/
        $returnData = "<xml>
<return_code>SUCCESS</return_code>
<return_msg></return_msg>
</xml>";

        return ['result' => $returnData];
    }

    /**
     * 获取模拟lis执行数据
     * @param $order_code
     * @return
     */
    public function execute($order_code)
    {
        $sql = "select ou.union_request_no,'E' as status from biz_order o 
    join biz_order_package_union opu on o.id = opu.order_id
    join intf_order_union ou on ou.order_pkg_union_id = opu.id and ou.order_id = opu.order_id
    join intf_union_relation ur on ur.ipeis_union_code = opu.union_code
    where opu.order_union_option != -1 and opu.delete_flag = 0 and opu.status = 0
    and ou.delete_flag = 0 and ou.status = 0 
    and ur.dept_code = 'LIS'
    and o.order_code = '{$order_code}'";

        $list = Db::connection('bs')
            ->select($sql);

        if ($list) {
            $data = array_map('get_object_vars', $list);
            foreach ($data as $item) {
                $xml = arrayToXml(['item' => $item],'xml');
                $rs = $this->requestIPEIS($xml,'LIS_STATUS');
                if ($rs['Body']['ResultCode'] == 1) {

                } else {
                    return fail($rs['Body']['ResultContent']);
                }
            }
            return success();
        } else {
            return fail('查找不到信息');
        }
    }

    /**
     * 获取模拟LIS报告数据
     * @param $order_code
     * @return
     */
    public function report($order_code)
    {
        $sql = "select ou.union_request_no,ur.his_union_code,ur.his_union_name,ou.union_barcode,opi.item_code,opi.item_name from biz_order o 
    join biz_order_package_union opu on o.id = opu.order_id
    join biz_order_package_item opi on opi.order_pkg_union_id = opu.id and opi.delete_flag = 0 and opi.status = 0
    join intf_order_union ou on ou.order_pkg_union_id = opu.id and ou.order_id = opu.order_id
    join intf_union_relation ur on ur.ipeis_union_code = opu.union_code
    where opu.order_union_option != -1 and opu.delete_flag = 0 and opu.status = 0
    and ou.delete_flag = 0 and ou.status = 0 
    and ur.dept_code in ('LIS')
    and o.order_code = '{$order_code}'";

        $list = Db::connection('bs')
            ->select($sql);

        if ($list) {
            $data = array_map('get_object_vars', $list);
            $report_list = [];
            foreach ($data as $item) {
                $item_check_result = rand(0,300);
                $report_list[$item['union_barcode']][] = [
                    'his_union_code' => $item['his_union_code'],
                    'his_union_name' => $item['his_union_name'],
                    'union_request_no' => '',//$item['union_request_no'],
                    'union_barcode' => $item['union_barcode'],
                    'item_code' => $item['item_code'],
                    'item_name' => $item['item_name'],
                    'examiner_id' => '',
                    'examiner_name' => '医生1',
                    'examiner_time' =>  date('Y-m-d H:i:s'),
                    'reviewer_id' => '',
                    'reviewer_name' => '医生2',
                    'reviewer_time' =>  date('Y-m-d H:i:s'),
                    'item_check_result' => $item_check_result,
                    'item_unit' => 'g/L',
                    'item_ref_value' => '50-200',
                    'exception_tag' => $item_check_result > 100 ? '↑' : ($item_check_result < 50 ? '↓' : ''),
                ];
            }

            foreach ($report_list as $report) {
                $xml = arrayToXml(['item' => $report],'xml');
                $rs = $this->requestIPEIS($xml,'LIS_REPORT');
                if ($rs['Body']['ResultCode'] == 1) {

                } else {
                    return fail($rs['Body']['ResultContent']);
                }
            }
            return success();
        } else {
            return fail('查找不到信息');
        }
    }



}