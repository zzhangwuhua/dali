<?php
/**
 * 模拟检查系统处理逻辑
 */
namespace App\Service\Demo;

use Illuminate\Support\Facades\DB;

class CheckDemoService extends BaseDemoService
{

    public function checkService($param)
    {
        switch ($param->func) {
            case 'sendCheckApply':
                return $this->sendCheckApply($param->message);
            case 'cancelCheckApply':
                return $this->cancelCheckApply($param->message);
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
     * 接收体检检查新增申请
     *
     * @param $params
     * @return string[]
     */
    public function sendCheckApply($params)
    {
        logInfo('接收参数'.$params,[],'CheckDemo_sendLisApply');
        /** 返回处理成功信息 **/
        $returnData = "<xml>
<return_code>SUCCESS</return_code>
<return_msg></return_msg>
</xml>";

        return ['result' => $returnData];
    }

    /**
     * 接收体检检查撤销申请
     *
     * @param $params
     * @return string[]
     */
    public function cancelCheckApply($params)
    {
        logInfo('接收参数'.$params,[],'CheckDemo_cancelLisApply');
        /** 返回处理成功信息 **/
        $returnData = "<xml>
<return_code>SUCCESS</return_code>
<return_msg></return_msg>
</xml>";

        return ['result' => $returnData];
    }


    /**
     * 获取模拟检查执行数据
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
    and ur.dept_code in ('LWRIS','LWUS','LWPIMS','LWEIS','ECG')
    and o.order_code = '{$order_code}'";

        $list = Db::connection('bs')
            ->select($sql);

        if ($list) {
            $data = array_map('get_object_vars', $list);
            foreach ($data as $item) {
                $xml = arrayToXml(['item' => $item],'xml');
                $rs = $this->requestIPEIS($xml,'CHECK_STATUS');
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


    public function report($order_code)
    {
        $sql = "select ou.union_request_no,ur.his_union_code,ur.his_union_name,ur.dept_code from biz_order o 
    join biz_order_package_union opu on o.id = opu.order_id
    join intf_order_union ou on ou.order_pkg_union_id = opu.id and ou.order_id = opu.order_id
    join intf_union_relation ur on ur.ipeis_union_code = opu.union_code
    where opu.order_union_option != -1 and opu.delete_flag = 0 and opu.status = 0
    and ou.delete_flag = 0 and ou.status = 0 
    and ur.dept_code in ('LWRIS','LWUS','LWPIMS','LWEIS','ECG')
    and o.order_code = '{$order_code}'";

        $list = Db::connection('bs')
            ->select($sql);

        if ($list) {

            $data = array_map('get_object_vars', $list);
            $report = [
                'examiner_id' => '',
                'examiner_name' => '张三',
                'examiner_time' =>  date('Y-m-d H:i:s'),
                'reviewer_id' => '',
                'reviewer_name' => '李四',
                'reviewer_time' => date('Y-m-d H:i:s'),
                'check_conclusion' => '结论结论结论结论结论结论结论结论结论结论结论结论结论结论结论',
                'report_data' => 'https://www.baidu.com/img/PCtm_d9c8750bed0b3c7d089fa7d55720d6cf.png',
            ];
            foreach ($data as $item) {
                $report['his_union_code'] = $item['his_union_code'];
                $report['his_union_name'] = $item['his_union_name'];
                $report['union_request_no'] = $item['union_request_no'];
                $report['check_desc'] = in_array($item['dept_code'],['LWRIS','LWUS','LWEIS']) ? '描述描述描述描述描述描述描述描述描述描述描述描述描述描述描述描述描述描述描述描述描述描述' : '';
                $xml = arrayToXml(['item' => $report],'xml');

                $rs = $this->requestIPEIS($xml,'CHECK_REPORT');
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