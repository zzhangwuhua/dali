<?php

namespace services\thirdpart\dlfsyy;

use extend\common\lib\StandapiClient;

class HisService extends \services\thirdpart\HisService
{
    /**
     * @return void
     * 同步his字典到 intf_his_union
     * 操作表 intf_his_union
     */
    public function syncHisDictToBs()
    {
        $res = StandapiClient::call_api_center('receive/sync_his_dict_to_bs', []);
        log_message("debug","【医嘱及细项同步intf_his_union】".json_encode($res,320),__FUNCTION__);

        if (!empty($res)){
            $start_time = microtime(true);
            foreach ($res as $key=>$val)
            {
                //把当前医嘱全部软删除 status = 1
                $update_data = ['status'=>1];
                $this->db->update('intf_his_union', $update_data, ['his_union_code' => $val['UNION_CODE'],'his_item_code'=>$val['ITEM_CODE']]);
                $flag = $this->check_intf_his_union($val['UNION_CODE'],$val['ITEM_CODE']);
                $union_py = $this->pinyin($val['UNION_NAME']);
                $item_py = $this->pinyin($val['ITEM_NAME']);
                if ($flag)
                {
                    $update_data = [
                        'status'=>0,
                        'union_class'=>$val['UNION_CLASS'],
                        'union_class_name'=>$val['UNION_CLASS_NAME'],
                        'mark'=>$val['MARK'],
                        'item_class'=>$val['ITEM_CLASS'],
                        'item_class_name'=>$val['ITEM_CLASS_NAME'],
                        'his_union_name'=>$val['UNION_NAME'],
                        'his_item_pinyin'=>$item_py,
                        'his_union_pinyin'=>$union_py,
                        'his_item_name'=>$val['ITEM_NAME'],
                        'his_item_price'=>$val['PRICE'],
                        'his_item_unit'=>$val['UNITS'],
                        'updated' => (new \DateTime())->format('Y-m-d H:i:s')
                    ];
                    //存在----更新
                    $this->db->update('intf_his_union', $update_data, ['his_union_code' => $val['UNION_CODE'],'his_item_code'=>$val['ITEM_CODE']]);
                }else{
                    $insert_data = [
                        'status'=>0,
                        'union_class'=>$val['UNION_CLASS'],
                        'union_class_name'=>$val['UNION_CLASS_NAME'],
                        'mark'=>$val['MARK'],
                        'item_class'=>$val['ITEM_CLASS'],
                        'item_class_name'=>$val['ITEM_CLASS_NAME'],
                        'his_union_name'=>$val['UNION_NAME'],
                        'his_item_pinyin'=>$item_py,
                        'his_union_pinyin'=>$union_py,
                        'his_item_name'=>$val['ITEM_NAME'],
                        'his_item_price'=>$val['PRICE'],
                        'his_item_unit'=>$val['UNITS'],
                        'his_union_code' => $val['UNION_CODE'],
                        'his_item_code' => $val['ITEM_CODE'],
                        'created' => (new \DateTime())->format('Y-m-d H:i:s')
                    ];
                    //不存在---新增
                    $this->db->insert('intf_his_union', $insert_data);
                }
            }
            $end_time = microtime(true);
            log_message("debug","intf_his_union同步完成 耗时---".round($end_time-$start_time,3)."---秒,当前服务器时间为---".date("Y-m-d H:i:s",time()),__FUNCTION__);
        }
    }



    /**
     * @param $his_union_code
     * @param $item_code
     * @return bool
     * 通过医嘱编码和细项编码判断是否存在
     */
    public function check_intf_his_union($his_union_code,$item_code)
    {
        $this->db->select('id',false);
        $this->db->where('his_union_code',$his_union_code);
        $this->db->where('his_item_code',$item_code);
        $this->db->where('delete_flag',0);
        $query = $this->db->get('intf_his_union');
        $res_result = $query->result_array();
        if (empty($res_result)) return false;
        return true;
    }

    public function pinyin($zh){
        $zh = $this->make_semiangle($zh);
        $ret = "";
        $s1 = iconv("UTF-8","gb2312", $zh);
        $s2 = iconv("gb2312","UTF-8", $s1);
        if($s2 == $zh){$zh = $s1;}
        for($i = 0; $i < strlen($zh); $i++){
            $s1 = substr($zh,$i,1);
            $p = ord($s1);
            if($p > 160){
                $s2 = substr($zh,$i++,2);
                $ret .= $this->getfirstchar($s2);
            }else{
                $ret .= $s1;
            }
        }
        return $ret;
    }

    public function make_semiangle($str){
        $arr = array('0' => '0', '1' => '1', '2' => '2', '3' => '3', '4' => '4', '5' => '5', '6' => '6', '7' => '7', '8' => '8', '9' => '9', 'A' => 'A', 'B' => 'B', 'C' => 'C', 'D' => 'D', 'E' => 'E', 'F' => 'F', 'G' => 'G', 'H' => 'H', 'I' => 'I', 'J' => 'J', 'K' => 'K', 'L' => 'L', 'M' => 'M', 'N' => 'N', 'O' => 'O', 'P' => 'P', 'Q' => 'Q', 'R' => 'R', 'S' => 'S', 'T' => 'T', 'U' => 'U', 'V' => 'V', 'W' => 'W', 'X' => 'X', 'Y' => 'Y', 'Z' => 'Z', 'a' => 'a', 'b' => 'b', 'c' => 'c', 'd' => 'd', 'e' => 'e', 'f' => 'f', 'g' => 'g', 'h' => 'h', 'i' => 'i', 'j' => 'j', 'k' => 'k', 'l' => 'l', 'm' => 'm', 'n' => 'n', 'o' => 'o', 'p' => 'p', 'q' => 'q', 'r' => 'r', 's' => 's', 't' => 't', 'u' => 'u', 'v' => 'v', 'w' => 'w', 'x' => 'x', 'y' => 'y', 'z' => 'z', '（' => '(', '）' => ')', '〔' => '[', '〕' => ']', '【' => '[', '】' => ']', '〖' => '[', '〗' => ']', '“' => '"', '”' => '"', '‘' => '\'', '’' => '\'', '｛' => '{', '｝' => '}', '《' => '<', '》' => '>', '％' => '%', '＋' => '+', '—' => '-', '－' => '-', '～' => '-', '：' => ':', '。' => '.', '、' => ',', '，' => ',', '；' => ';', '？' => '?', '！' => '!', '…' => '...', '‖' => '|', '｜' => '|', '〃' => '"', '　' => ' ');
        return strtr($str, $arr);
    }

    public function getfirstchar($s0){
        $fchar = ord($s0{0});
        if($fchar >= ord("A") and $fchar <= ord("z") )return strtoupper($s0{0});
        $s1 = iconv("UTF-8","gb2312", $s0);
        $s2 = iconv("gb2312","UTF-8", $s1);
        if($s2 == $s0){$s = $s1;}else{$s = $s0;}
        $asc = ord($s{0}) * 256 + ord($s{1}) - 65536;
        if($asc >= -20319 and $asc <= -20284) return "A";
        if($asc >= -20283 and $asc <= -19776) return "B";
        if($asc >= -19775 and $asc <= -19219) return "C";
        if($asc >= -19218 and $asc <= -18711) return "D";
        if($asc >= -18710 and $asc <= -18527) return "E";
        if($asc >= -18526 and $asc <= -18240) return "F";
        if($asc >= -18239 and $asc <= -17923) return "G";
        if($asc >= -17922 and $asc <= -17418) return "H";
        if($asc >= -17417 and $asc <= -16475) return "J";
        if($asc >= -16474 and $asc <= -16213) return "K";
        if($asc >= -16212 and $asc <= -15641) return "L";
        if($asc >= -15640 and $asc <= -15166) return "M";
        if($asc >= -15165 and $asc <= -14923) return "N";
        if($asc >= -14922 and $asc <= -14915) return "O";
        if($asc >= -14914 and $asc <= -14631) return "P";
        if($asc >= -14630 and $asc <= -14150) return "Q";
        if($asc >= -14149 and $asc <= -14091) return "R";
        if($asc >= -14090 and $asc <= -13319) return "S";
        if($asc >= -13318 and $asc <= -12839) return "T";
        if($asc >= -12838 and $asc <= -12557) return "W";
        if($asc >= -12556 and $asc <= -11848) return "X";
        if($asc >= -11847 and $asc <= -11056) return "Y";
        if($asc >= -11055 and $asc <= -10247) return "Z";
        return null;
    }

    /**
     * 同步intf_his_union 表到 dict字典表
     * 操作表 dict_union、dict_item、dict_union_item、intf_union_relation
     *
     * @return void
     *
     */
    public function syncBsDict()
    {
        $this->db->where_in('union_class', ['UC','UL']);
//        $this->db->group_by('his_union_code');
        $this->db->order_by('id asc');
        $query = $this->db->get('intf_his_union');
        $res_result = $query->result_array();

        if (!empty($res_result)){
            $start_time = microtime(true);
            foreach ($res_result as $k=>$v){
                $this->sync_bs_dict_check($v['his_union_code'],
                    $v['his_union_name'],
                    $v['his_union_pinyin'],
                    $v['his_item_code'],
                    $v['his_item_name'],
                    $v['his_item_price'],
                    $v['item_class_name'],
                    $v['his_item_unit'],
                    $v['mark']);

            }
            $end_time = microtime(true);
            log_message("debug","bs字典同步完成 耗时---".round($end_time-$start_time,3)."---秒,当前服务器时间为---".date("Y-m-d H:i:s",time()),__FUNCTION__);
        }
    }

    /**
     * DESC: 获取同步数据
     * Author: Lemon
     * @param $mark
     * @param $itemClassName
     * @param $hisUnionName
     * @return mixed
     */
    private function getSyncBsBaseDict($mark, $itemClassName, $hisUnionName)
    {
        switch ($mark) {
            case '心电图检查':
                $data['exam_dept_id'] = '34';
                $data['exam_dept_code'] = '11';
                $data['work_group_id'] = '133';
                $data['work_group_code'] = 'WG1230';
                $data['union_type_id'] = '1392';
                $data['union_type_code'] = '02';
                //intf
                $data['dept_code'] = 'ECG';
                $data['dept_name'] = '心电图';
//                    $exe_code='1305';
                $data['exe_name'] = '心电室';
                break;
            case 'MRI检查':
                $data['exam_dept_id'] = '27';
                $data['exam_dept_code'] = '18';
                $data['work_group_id'] = '12';
                $data['work_group_code'] = 'WG0120';
                $data['union_type_id'] = '1392';
                $data['union_type_code'] = '02';
                //intf
                $data['dept_code'] = 'LWRIS';
                $data['dept_name'] = '放射科';
//                    $exe_code='1305';
                $data['exe_name'] = '放射科';
                break;
            case 'CT检查':
                $data['exam_dept_id'] = '21';
                $data['exam_dept_code'] = '15';
                $data['work_group_id'] = '11';
                $data['work_group_code'] = 'WG0110';
                $data['union_type_id'] = '1392';
                $data['union_type_code'] = '02';
                //intf
                $data['dept_code'] = 'LWRIS';
                $data['dept_name'] = '放射科';
//                    $exe_code='1305';
                $data['exe_name'] = '放射科';
                break;
            case 'DR检查(儿童中)':
            case 'DR检查(儿童大)':
            case 'DR检查(儿童小)':
            case 'DR检查(成人)':
                $data['exam_dept_id'] = '20';
                $data['exam_dept_code'] = '08';
                $data['work_group_id'] = '10';
                $data['work_group_code'] = 'WG0100';
                $data['union_type_id'] = '1392';
                $data['union_type_code'] = '02';
                //intf
                $data['dept_code'] = 'LWRIS';
                $data['dept_name'] = '放射科';
                //                    $exe_code='1305';
                $data['exe_name'] = '放射科';
                break;
            case '眼科检查':
                $data['exam_dept_id'] = '14';
                $data['exam_dept_code'] = '04';
                $data['work_group_id'] = '5';
                $data['work_group_code'] = 'WG0050';
                $data['union_type_id'] = '1392';
                $data['union_type_code'] = '01';
                //intf
                $data['dept_code'] = 'HIS';
                $data['dept_name'] = 'HIS系统';
                //                    $exe_code='1305';
                $data['exe_name'] = '';
                break;
            case '肺功能检查':
                $data['exam_dept_id'] = '40';
                $data['exam_dept_code'] = '43';
                $data['work_group_id'] = '137';
                $data['work_group_code'] = 'WG1270';
                $data['union_type_id'] = '1392';
                $data['union_type_code'] = '01';
                //intf
                $data['dept_code'] = '';
                $data['dept_name'] = '';
                //                    $exe_code='1305';
                $data['exe_name'] = '';
                break;
            case '骨密度检查':
                $data['exam_dept_id'] = '77';
                $data['exam_dept_code'] = '51';
                $data['work_group_id'] = '33';
                $data['work_group_code'] = 'WG0330';
                $data['union_type_id'] = '1392';
                $data['union_type_code'] = '04';
                //intf
                $data['dept_code'] = 'NONSTANDARD';
                $data['dept_name'] = 'HIS系统';
                //                    $exe_code='1305';
                $data['exe_name'] = '';
                break;
            case '超声检查':
                $data['exam_dept_id'] = '70';
                $data['exam_dept_code'] = '23';
                $data['work_group_id'] = '26';
                $data['work_group_code'] = 'WG0260';
                $data['union_type_id'] = '1392';
                $data['union_type_code'] = '02';
                //intf
                $data['dept_code'] = 'LWUS';
                $data['dept_name'] = '超声科';
                //                    $exe_code='1305';
                $data['exe_name'] = '超声科';
                break;
            case '消化内镜碳呼气检查':
                $data['exam_dept_id'] = '103';
                $data['exam_dept_code'] = '0003';
                $data['work_group_id'] = '93';
                $data['work_group_code'] = 'WG0830';
                $data['union_type_id'] = '1394';
                $data['union_type_code'] = '04';
                //intf
                $data['dept_code'] = 'NONSTANDARD';
                $data['dept_name'] = 'HIS';
                //                    $exe_code='1305';
                $data['exe_name'] = '';
                break;
            case '消化内镜检查':
            case '消化内镜肠镜检查':
                $data['exam_dept_id'] = '107';
                $data['exam_dept_code'] = '0032';
                $data['work_group_id'] = '104';
                $data['work_group_code'] = 'WG0940';
                $data['union_type_id'] = '1394';
                $data['union_type_code'] = '04';
                //intf
                $data['dept_code'] = 'His';
                $data['dept_name'] = 'HIS系统';
                //                    $exe_code='1305';
                $data['exe_name'] = '';
                break;
            default:
                if ('化验费' == $itemClassName){
                    $data['exam_dept_id'] = '67';
                    $data['exam_dept_code'] = '07';
                    $data['work_group_id'] = '23';
                    $data['work_group_code'] = 'WG0230';
                    $data['union_type_id'] = '1392';
                    $data['union_type_code'] = '03';
                    //intf
                    $data['dept_code'] = 'LIS';
                    $data['dept_name'] = '检验科';
                    //                    $exe_code='1305';
                    $data['exe_name'] = '';
                    break;
                }
                if (false !== strpos($hisUnionName, '磁共振')){
                    $data['exam_dept_id'] = '27';
                    $data['exam_dept_code'] = '18';
                    $data['work_group_id'] = '12';
                    $data['work_group_code'] = 'WG0120';
                    $data['union_type_id'] = '1392';
                    $data['union_type_code'] = '02';
                    //intf
                    $data['dept_code'] = 'LWRIS';
                    $data['dept_name'] = '放射科';
//                    $exe_code='1305';
                    $data['exe_name'] = '放射科';
                    break;
                }
                if (false !== strpos($hisUnionName, 'B超')){
                    $data['exam_dept_id'] = '70';
                    $data['exam_dept_code'] = '23';
                    $data['work_group_id'] = '26';
                    $data['work_group_code'] = 'WG0260';
                    $data['union_type_id'] = '1392';
                    $data['union_type_code'] = '02';
                    //intf
                    $data['dept_code'] = 'LWUS';
                    $data['dept_name'] = '超声科';
                    //                    $exe_code='1305';
                    $data['exe_name'] = '超声科';
                    break;
                }
                //其他类
                $data['exam_dept_id'] = '79';
                $data['exam_dept_code'] = 'X01';
                $data['work_group_id'] = '35';
                $data['work_group_code'] = 'WG0350';
                $data['union_type_id'] = '1396';
                $data['union_type_code'] = '06';
                //intf
                $data['dept_code'] = 'HIS';
                $data['dept_name'] = 'HIS系统';
                //                    $exe_code='1305';
                $data['exe_name'] = '';
                break;
        }

        return $data;
    }

    /**
     * DESC: 修改同步项目
     * Author: Lemon
     * @param $his_item_code
     * @param $his_item_name
     * @param $his_item_price
     * @param $item_unit
     * @return int|mixed
     */
    private function saveSyncBsItem($his_item_code, $his_item_name, $his_item_price, $item_unit)
    {
        $res_item_result = $this->db->select('id', false)->where('item_code', $his_item_code)->get('dict_item')->row_array();

        $updateData = [
            'item_name' => $his_item_name,
            'item_price' => $his_item_price,
            'item_unit' => $item_unit,
        ];

        if ($res_item_result){
            //存在 修改
            $this->db->update('dict_item', $updateData, ['item_code' => $his_item_code]);
            $itemId = $res_item_result['id'];
        }else{
            //新增
            $insertData = [
                'item_code' => $his_item_code,
                'item_class_id' => 685,
                'item_class_code' => 'A',
                'item_branch_id' => 716,
                'item_branch_code' => 'A1',
                'item_method_id' => '873',
                'item_method_code' => 'GM',
                'item_type_id' => 1421,
                'item_type_code' => '1',
                'item_default_result' => '正常',
                'item_data_type_id' => 1430,
                'item_data_type_code' => '0',
                'item_gender_rel_id' => 1428,
                'item_gender_rel_code' => '1'
            ];
            $updateData = array_merge($updateData, $insertData);

            $this->db->insert('dict_item', $updateData);
            $itemId = $this->db->insert_id();
        }
        return $itemId;
    }

    //处理dict_union 表 和 dict_item 表 和 dict_union_item 表 和 intf_union_relation 表
    public function sync_bs_dict_check($his_union_code,$his_union_name,$his_union_pinyin,$his_item_code,$his_item_name,$his_item_price,$item_class_name,$item_unit,$mark)
    {
        $this->db->select('id', false);
        $this->db->where('union_code', $his_union_code);
        $query = $this->db->get('dict_union');
        $res_result = $query->row_array();

        $dsBaseDict = $this->getSyncBsBaseDict($mark, $item_class_name, $his_union_name);

        try {
            $this->db->trans_start();
            $item_id = $this->saveSyncBsItem($his_item_code,$his_item_name,$his_item_price,$item_unit);

            if ($res_result) {
                $union_id = $res_result['id'];
                log_message($union_id, 'sync_bs_dict_error');
                //判断关联关系是否存在
                $union_item = $this->db->select('id', false)
                    ->where('union_id',$union_id)
                    ->where('item_id', $item_id)
                    ->get('dict_union_item')
                    ->result_array();
                if (count($union_item) == 0){
                    //不存在
                    $insert_union_item = ['union_id' => $union_id, 'item_id' => $item_id];
                    $this->db->insert('dict_union_item', $insert_union_item);
                }
//                $his_union_code 有问题 注释掉
//                $hisUnionPriceRet = $this->db->from('dict_union_item')
//                    ->select_sum('item_price')
//                    ->join('dict_item', 'dict_item.id = dict_union_item.item_id')
//                    ->where('dict_union_item.union_id', $union_id)
//                    ->where('dict_union_item.status', 0)
//                    ->where('dict_union_item.delete_flag', 0)
//                    ->get()
//                    ->result_array();

                $hisUnionPriceRet = $this->db->from('intf_his_union')
                    ->select_sum('his_item_price')
                    ->where('his_union_code', $his_union_code)
                    ->get()
                    ->row_array();

                $his_union_price = $hisUnionPriceRet['his_item_price'] ?? 0;
                //添加判断 更新科室信息
                $update_data = [
                    'union_fee' => $his_union_price,
                    'updated' => date("Y-m-d H:i:s", time()),
                    'exam_dept_id' => $dsBaseDict['exam_dept_id'],
                    'exam_dept_code' => $dsBaseDict['exam_dept_code'],
                    'work_group_id' => $dsBaseDict['work_group_id'],
                    'work_group_code' => $dsBaseDict['work_group_code'],
                    'union_type_id' => $dsBaseDict['union_type_id'],
                    'union_type_code' => $dsBaseDict['union_type_code'],
                ];

                //更新 intf_union_relation
                $up_union_relation = [
//                'sample_type_name'=>$his_union_method_code,
//                'sample_type_code'=>$his_union_method,
                    'dept_code' => $dsBaseDict['dept_code'],
                    'dept_name' => $dsBaseDict['dept_name'],
                    'exe_dept_name' => $dsBaseDict['exe_name']
                ];

                //更新
                $this->db->update('dict_union', $update_data, ['union_code' => $his_union_code]);

                $this->db->update('intf_union_relation', $up_union_relation, ['his_union_code' => $his_union_code]);

            } else {
                //新增
                $insert_union = [
                    'discount_flag' => 1,
                    'is_complete' => 0,
                    'is_often_use' => 0,
                    'union_code' => $his_union_code,
                    'union_name' => $his_union_name,
                    'union_fee' => $his_item_price,
                    'exam_dept_id' => $dsBaseDict['exam_dept_id'],
                    'exam_dept_code' => $dsBaseDict['exam_dept_code'],
                    'work_group_id' => $dsBaseDict['work_group_id'],
                    'work_group_code' => $dsBaseDict['work_group_code'],
                    'union_type_id' => $dsBaseDict['union_type_id'],
                    'union_type_code' => $dsBaseDict['union_type_code'],
                    'union_gender_rel' => 0,
                    'union_barcode_flag' => $dsBaseDict['dept_name'] == '检验科' ? 0 : 1,
                    'union_blood_flag' => $dsBaseDict['dept_name'] == '检验科' ? 1 : 0,
                    'union_report_flag' => $dsBaseDict['dept_name'] == '检验科' ? 0 : 1,
                    'barcode_type' => $dsBaseDict['dept_name'] == '检验科' ? 2 : 0,
                    'union_help_code' => $his_union_pinyin
                ];

                $insert_union_relation = [
                    'ipeis_union_code' => $his_union_code,
                    'ipeis_union_name' => $his_union_name,
                    'his_union_code' => $his_union_code,
                    'his_union_name' => $his_union_name,
                    'dept_code' => $dsBaseDict['dept_code'],
                    'dept_name' => $dsBaseDict['dept_name'],
                    'union_price' => $dsBaseDict['his_union_price'],
//                'sample_type_name'=>$dept_name=='检验科'?$his_union_method:'',
//                'sample_type_code'=>$dept_name=='检验科'?$his_union_method_code:'',
                    'exe_dept_code' => $dsBaseDict['dept_name'],
                    'exe_dept_name' => $dsBaseDict['exe_name']
                ];

                $this->db->insert('dict_union', $insert_union);
                $union_id = $this->db->insert_id();

                $insert_union_item = ['union_id' => $union_id, 'item_id' => $item_id];
                $this->db->insert('dict_union_item', $insert_union_item);

                $this->db->insert('intf_union_relation', $insert_union_relation);
            }

            $this->db->trans_commit();
            return true;
        } catch (\Exception $e) {
            $this->db->trans_rollback();
            log_message("debug","bs字典同步完成 耗时---".$e->getMessage(),'sync_bs_dict_error');
            return ['error' => 'dict_union' . $e->getMessage()];
        }
    }
}