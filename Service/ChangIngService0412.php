<?php
/**
 * DESC:
 * name: Lemon
 */

namespace App\Service;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ChangIngService
{
    public static function callback()
    {
        $hisDb = config('standapi.his');
        $oracle = new OracleService($hisDb['user'], $hisDb['password'], $hisDb['host']);

        $sql = "SELECT 处方号 as recipe_no
	,执行科室 as dept_code
	,交易类型 as pay_type
	,是否收费 as status
	,收费时间 as pay_time
	,发票号 as invoice_no
FROM HIS.XKTJ_TJSFXX where 是否收费 = '已收费' AND 收费时间 > sysdate-2/60*24 order by 收费时间 asc, 处方号 asc";
        $payData = $oracle->oracle_fetch_array($sql);

        logInfo('缴费申请数据 post -> ',[$sql], 'callback');

        if (empty($payData)){
            return;
        }
        //添加测试发票号
//        foreach ($payData as $key => $vo){
//            $payData[$key]['INVOICE_NO'] = '12345';
//        }
        //添加测试发票号结束

        //转存数据
        $payList = collect($payData)->groupBy('INVOICE_NO');
        logInfo('缴费申请数据 post -> ', [$payList], 'callback8888');
        foreach ($payList as $key => $vo){
            //判断key的状态是否一致
            $status = 1;
            if ('正交易' == $vo[0]['PAY_TYPE']){
                $status = 2;
            }else if('反交易' == $vo[0]['PAY_TYPE']){
                $status = 3;
            }

            $currentTime = (new \DateTime())->format('Y-m-d H:i:s');
            $backData = DB::table('intf_his_charging_back_data')->where('invoice_no', $key)->first();

            $recipeNo = array_unique(array_column($vo->toArray(), 'RECIPE_NO')); //处方号
            if (!$backData){
                //新增并且发送数据导bs
                $insertData['invoice_no'] = $key;
                $insertData['recipe_no'] = json_encode($recipeNo, 320);
                $insertData['invoice_desc'] = '';
                $insertData['status'] = $status;
                $insertData['created'] = $currentTime;
                $insertData['updated'] = $currentTime;
                $ret = DB::table('intf_his_charging_back_data')->insert($insertData);
            }else{
                //判断状态是否一致
                if ($status == $backData->status){
                    //状态一致continue
                    continue;
                }
                //状态不一致//发送数据到bs， bs处理完改变状态
                $updateData['status'] = $status;
                $updateData['updated'] = $currentTime;
                $ret = DB::table('intf_his_charging_back_data')->where('id', $backData->id)->update($updateData);
                if ($ret > 0){
                    $ret = true;
                }
            }
            if ($ret && 1 != $status){
                //发送bs
                $sendData['type'] = $status;  //2缴费  3退款
                $sendData['invoice_no'] = $key;
                $sendData['recipe_no'] = $recipeNo;
                $sendData['invoice_desc'] = '';

                self::toBsData($sendData);
            }
        }
    }

    /**
     * DESC: 医保
     * name: Lemon
     * @param $orderInfo
     * @param $customer
     * @param $union_item
     * @param $operator
     * @param $operatorId
     * @return array
     */
    public function charging($orderInfo, $customer, $union_item, $operator, $operatorId)
    {
        try {
            //查询订单下的所有项目 未支付
            $hasRecipeNo = DB::table('intf_his_charging_data')
                ->where('order_id', $orderInfo['order_id'])
                ->where('status', 1)
                ->pluck('recipe_no')
                ->toArray();

            $hasItemList = [];
            if (!empty($hasRecipeNo)){
                //获取项目
                $hasItem = DB::table('intf_his_charging_item')->whereIn('recipe_no', $hasRecipeNo)->get()
                    ->map(function ($value){
                        return (array)$value;
                    })->toArray();
                if (!empty($hasItem)){
                    foreach ($hasItem as $vo){
                        $hasItemList[] = $vo['union_code'] . '--' . $vo['item_code'];
                    }
                }
            }
            //排除在$hasItemList中的项目
            $new_union_item = [];
            if (!empty($hasItemList)){
                foreach ($union_item as $item){
                    if (in_array($item['union_code'] . '--' . $item['item_code'], $hasItemList)){
                        continue;
                    }else{
                        $new_union_item[] = $item;
                    }
                }
            }else{
                $new_union_item = $union_item;
            }

            if (empty($new_union_item)){
                //返回
                return ['status' => true];
            }
            //通过item_code 分组数组
            $new_union_item = collect($new_union_item)->groupBy('item_code')->toArray();

            logInfo('缴费申请数据 post -> ', $new_union_item, 'charging');

            //建档，保存省份证已 建档号

            $hisDb = config('standapi.his');

            $link = oci_connect($hisDb['user'], $hisDb['password'], $hisDb['host'], 'UTF8');

            $currentTime = (new \DateTime())->format('Y-m-d H:i:s');
//            $archive = DB::table('intf_his_charging_archive')->where('id_card', $customer['cust_id_card'])->first();
//            if ($archive){
//                $his_archives_no = $archive->archives_no;
//            }else{
//                $putRecordRet = $this->sendPutRecord($link, $customer, $operator);
//                $his_archives_no = $putRecordRet[0];
//                $insertArchiveData['id_card'] = $customer['cust_id_card'];
//                $insertArchiveData['name'] = $customer['cust_name'];
//                $insertArchiveData['archives_no'] = $his_archives_no;
//                $insertArchiveData['created'] = $currentTime;
//                DB::table('intf_his_charging_archive')->insert($insertArchiveData);
//            }

            //挂号
            $registerRet = $this->sendRegister($link, $customer, $operator);

            $clinicCode = $registerRet[0];

            //生成处方号
            $recipeNo = $this->getRecipeNo($link);
            $insertHisChargingData = [
                'order_id' => $orderInfo['order_id'],
                'recipe_no' => $recipeNo,
                'clinic_no' => $clinicCode,
                'operator_id' => $operatorId,
                'created' => $currentTime,
            ];
            DB::table('intf_his_charging_data')->insert($insertHisChargingData);

            //循环发送医保
            $i = 1;
            foreach ($new_union_item as $key => $item){
                //医保发送成功保存到数据库
                $sendChargingData = [
                    'his_archives_no' => $orderInfo["medical_record_no"],
                    'clinicCode' => $clinicCode,
                    'recipe_no' => $recipeNo,
                    'item_no' => $i,
                    'item_code' => $key,
                    'item_price' => array_sum(array_column($item, 'item_price')),
                    'qty' => array_sum(array_column($item, 'qty')),
                    'dept_code' => $item[0]['his_dept_code'],
                    'doctor' => $orderInfo['empl_code'] ?? 'admin'
                ];
                $insertChargingData = [];
                foreach ($item as $v){
                    $insertChargingData[] =   [
                        'order_id' => $orderInfo['order_id'],
                        'recipe_no' => $recipeNo,
                        'item_no' => $i,
                        'union_code' => $v['union_code'],
                        'item_code' => $v['item_code'],
                        'qty' => $v['qty'],
                        'item_price' => $v['item_price'],
                        'doctor' => $orderInfo['empl_code'] ?? 'admin',
                        'dept_code' => $v['his_dept_code'],
                        'created' => $currentTime,
                    ];
                }
                logInfo('医保申请 post -> ', $sendChargingData, 'charging');
                logInfo('医保申请操作人 post -> ', [$operator], 'charging');
                $chargingRet = $this->sendCharging($link, $sendChargingData, $operator);
                DB::table('intf_his_charging_item')->insert($insertChargingData);
                $i++;
            }

            return ['status' => true];
        }catch (\Exception $ex){
            logInfo('charging检查申请 post -> ', [$ex->getMessage()], 'charging');
            return  ['status' => false, 'msg' => $ex->getMessage()];
        }
    }

    /**
     * DESC: 划价删除
     * name: Lemon
     * @param $orderInfo
     * @param $customer
     * @param $operator
     * @param $operatorId
     * @return array
     */
    public function chargingDel($orderInfo, $customer, $operator, $operatorId)
    {
        try {
//            DB::connection()->enableQueryLog();
            //查询订需要删除划价的门诊流水号（也就是挂号返回的）和处方号
            $chargingList = DB::table('intf_his_charging_item as ihci')
                ->select(DB::raw("DISTINCT ihci.recipe_no,ihcd.clinic_no,ihcd.id"))
                ->leftJoin('intf_his_charging_data as ihcd', 'ihci.recipe_no', '=', 'ihcd.recipe_no')
                ->where('ihci.order_id', $orderInfo['order_id'])
                ->where('ihcd.status', 1)
                ->whereIn('ihci.union_code', explode(',',$orderInfo["union_codes"]))
                //->groupBy("ihci.recipe_no","ihcd.clinic_no")
                ->get()
                ->map(function ($value){
                    return (array)$value;
                })
                ->toArray();

            //logInfo('划价删除 sql -> ',[DB::getQueryLog()], 'chargingDel8888');
            logInfo('划价删除 post -> ',$chargingList, 'chargingDel8888');

            $hisDb = config('standapi.his');

            $link = oci_connect($hisDb['user'], $hisDb['password'], $hisDb['host'], 'UTF8');

            //循环发送
            foreach ($chargingList as $key => $item){
                //医保发送成功保存到数据库
                $sendChargingData = [
                    'CARDNO_IN' => $orderInfo["medical_record_no"],
                    'CLINICCODE_IN' => $item["clinic_no"],
                    'RECIPENO_IN' => $item["recipe_no"],
                ];

                logInfo('划价删除 post -> ', $sendChargingData, 'chargingDel');
                $chargingRet = $this->sendChargingDel($link, $sendChargingData, $operator);
                //DB::connection()->enableQueryLog();
                //更新费用申请状态
                DB::table('intf_his_charging_data')
                    ->where("status",1)
                    ->where("id",$item['id'])
                    ->update(["status"=>3]);
                //logInfo('划价删除 sql -> ',[DB::getQueryLog()], 'chargingDel8888');
            }

            return ['status' => true];
        }catch (\Exception $ex){
            logInfo('chargingDel检查申请 post -> ', [$ex->getMessage()], 'chargingDel');
            return  ['status' => false, 'msg' => $ex->getMessage()];
        }
    }
    public function getCardType($cust_card_type_code){

        //处理身份证类型
        switch ($cust_card_type_code)
        {
            case 1:
                $card_type = '01';
                break;
            case 2:
                $card_type = '03';
                break;
            case 3:
                $card_type = '06';
                break;
            case 4:
                $card_type = '07';
                break;
            default:
                $card_type = '99';
        }

        return $card_type;

    }

    /**
     * DESC: 建档
     * name: Lemon
     * @param $link
     * @param $customer
     * @param $operator
     * @return array '{$customer['cust_nation_code']}','{$customer['province']}',
    '{$customer['city']}',
    '{$customer['area']}',
    '{$customer['cust_address']}',
     * @throws \Exception
     */
    public function sendPutRecord($link, $customer, $operator)
    {

        $card_type = $this->getCardType($customer['cust_card_type_code']);

        $type = $customer['order_cmp_name']?1:0;
	$cust_nation_code = $customer['cust_nation_code'] == 0 ? 1 : $customer['cust_nation_code'];
        $putRecordSql = "BEGIN 
        XKTJ_PEIS_RECORD(
        '{$type}',
        '{$customer['cmp_org_code']}',
        '{$customer['cust_name']}',
        '{$customer['cust_sex']}',
        TO_DATE('{$customer['cust_birthday']}','YYYY-MM-DD HH24:MI:SS'),
        '{$card_type}',
        '{$customer['cust_id_card']}',
        '{$cust_nation_code}',
        '{$customer['cust_mobile']}',
        '{$customer['profession_code']}',
        '{$customer['province']}',
        '{$customer['city']}',
        '{$customer['area']}',
        '{$customer['cust_address']}',
        '{$operator}',
        :ret
        );
        END;";
        $putRecordRet = '';
        logInfo('检查申请挂号 post -> ', [$putRecordSql], 'charging3333');
        $ret = $this->oracleQuery($link, $putRecordSql, ':ret', $putRecordRet, 200);
        logInfo('检查申请挂号 post -> ', [$ret], 'charging444444');
        if (false === $ret){
            throw new \Exception('建档失败');
        }
        logInfo('检查申请挂号 post -> ', [$putRecordRet], 'charging88888');
        $putRecordRet = explode('|',$putRecordRet);
        if (1 != $putRecordRet[0]){
            throw new \Exception($putRecordRet[1]);
        }
        return explode('^', $putRecordRet[1]);
    }

    /**
     * DESC: 挂号
     * name: Lemon
     * @param $link
     * @param $customer
     * @param $operator
     * @return array
     * @throws \Exception
     */
    private function sendRegister($link, $customer, $operator)
    {
        $card_type = $this->getCardType($customer['cust_card_type_code']);
        $registerSql = "BEGIN 
        XKTJ_PEIS_REGISTER(
        '{$card_type}',
        '{$customer['cust_id_card']}',
        '{$customer['cust_name']}',
        '{$operator}',
        :ret
        );
        END;";
        $registerRet = '';
        $ret = $this->oracleQuery($link, $registerSql, ':ret', $registerRet, 200);
        if (false === $ret){
            throw new \Exception('挂号失败');
        }
        logInfo('检查申请挂号 post -> ', [$registerSql], 'charging1111111');
        logInfo('检查申请挂号 post -> ', [$registerRet], 'charging');
        $registerRet = explode('|',$registerRet);
        if (1 != $registerRet[0]){
            throw new \Exception($registerRet[1]);
        }
        return explode('^', $registerRet[1]);
    }

    /*
     *申请划价
     */
    private function sendCharging($link, $params, $operator)
    { //'{$params['doctor']}', 开单医生传参错误，无引用        '{$total}', 金额
//        $total = sprintf("%.2f",$params['qty'] * $params['item_price']);
        logInfo('更新存储过程结果',['111111'], 'charging35');
        $chargingSql = "BEGIN 
        XKTJ_PEIS_CHARGING(
        '{$params['his_archives_no']}',
        '{$params['clinicCode']}',
        '{$params['recipe_no']}',
        '{$params['item_no']}',
        '{$params['item_code']}',
        '{$params['qty']}',
        '{$params['dept_code']}',
        '100132',
        '1014',
        '{$operator}',
        :ret
        );
        END;";
        $chargingRet = '';
        logInfo('更新存储过程数据', [$chargingSql], 'charging123');
        $ret = $this->oracleQuery($link, $chargingSql, ':ret', $chargingRet, 200);
        if (false === $ret){
            throw new \Exception('划价失败');
        }
        $chargingRet = explode('|',$chargingRet);
        logInfo('更新存储过程结果', $chargingRet, 'charging345');
        if (1 != $chargingRet[0]){
            throw new \Exception($chargingRet[1]);
        }
        return explode('^', $chargingRet[1]);
    }

    /*
     * 划价删除
     */
    private function sendChargingDel($link, $params, $operator)
    {
        $chargingSql = "BEGIN 
        XKTJ_PEIS_RECIPENODEL(
        '{$params['CARDNO_IN']}',
        '{$params['CLINICCODE_IN']}',
        '{$params['RECIPENO_IN']}',
        '{$operator}',
        :ret
        );
        END;";
        $chargingRet = '';
        logInfo('更新存储过程数据', [$chargingSql], 'chargingDel');
        $ret = $this->oracleQuery($link, $chargingSql, ':ret', $chargingRet, 200);
        if (false === $ret){
            throw new \Exception('划价删除失败');
        }
        $chargingRet = explode('|',$chargingRet);
        logInfo('更新存储过程结果', $chargingRet, 'chargingDel');
        if (1 != $chargingRet[0]){
            throw new \Exception($chargingRet[1]);
        }
        return explode('^', $chargingRet[1]);
    }

    /**
     * DESC: 处方号获取失败
     * name: Lemon
     * @param $link
     * @return mixed
     * @throws \Exception
     */
    private function getRecipeNo($link)
    {
        $sql = "Select HIS.SEQ_OPB_RECIPE_NO.nextval FROM dual";
        $stmt = OCIParse($link, $sql);
        $r = OCIExecute($stmt);
        if (!$r) {
            logMessage("oracle执行错误". $this->oracle_error($r)."---sql为--".$sql, 'charging','debug');
            throw new \Exception('处方号获取失败');
        }
        $data = oci_fetch_assoc($stmt);

        if (empty($data)){
            throw new \Exception('处方号获取失败');
        }
        return $data['NEXTVAL'];
    }

    /**
     * DESC: 调用oracle存储过程
     * name: Lemon
     * @param $link
     * @param $sql
     * @param null $retField
     * @param null $retValue
     * @param int $retLen
     * @return bool
     */
    private function oracleQuery($link, $sql, $retField = null, &$retValue = null, $retLen = 200)
    {
        $stmt = OCIParse($link, $sql);
        if (!empty($retField)){
            OCIBindByName($stmt, $retField, $retValue, $retLen);
        }
        $r = OCIExecute($stmt);
        if (!$r) {
            Log::debug("oracle执行错误". $this->oracle_error($r)."---sql为--".$sql);
            return false;
        }

        return true;
    }

    private function oracle_error($stmt)
    {
        if (!empty($stid)) {
            $e = oci_error($stmt);
            trigger_error(htmlentities($e['message']), E_USER_ERROR);
        }
    }

    private static function toBsData($sendData)
    {
        $charging_data = DB::table('intf_his_charging_data')->select(['order_id','operator_id','status'])->whereIn('recipe_no', $sendData['recipe_no'])->first();


        $data = DB::table('intf_his_charging_item')->whereIn('recipe_no', $sendData['recipe_no'])
            ->pluck('union_code')
            ->toArray();

        $orderId = $charging_data->order_id;
        $operatorId = $charging_data->operator_id;

        //测试模拟result参数
        $sendData['invoice_desc'] = [
            'invoice_no' => $sendData['invoice_no'],
            'recipe_no' => $sendData['recipe_no'],
        ];
        //测试模拟result参数结束

        $returnData['invoice_no'] = $sendData['invoice_no'];
        $returnData['invoice_desc'] = $sendData['invoice_desc'];
        $returnData['recipe_no'] = $sendData['recipe_no'];
        $returnData['type'] = $sendData['type'];
        $returnData['order_id'] = $orderId;
        $returnData['operator_id'] = $operatorId;
        $returnData['union_code'] = $data;
        $returnData['pay_time'] = (new \DateTime())->format('Y-m-d H:i:s');

        $ret = toBS('charging_back', $returnData);
        if ($ret['status'] && 1 == $ret['data']['status']){
            //付款修改对应处方状态
            if (2 == $sendData['type']){
                DB::table('intf_his_charging_data')->whereIn('recipe_no', $sendData['recipe_no'])->update(['status' => 2]);
            }
            return;
        }else{
            //修改回去
            DB::table('intf_his_charging_back_data')->where('invoice_no', $returnData['invoice_no'])->update(['status' => $returnData['type'] - 1]);
        }
//        logInfo('医保结果 post -> ', $ret, 'charging');
    }
}
