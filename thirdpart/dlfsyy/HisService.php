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

    /**
     * DESC: 医保
     * name: Lemon
     * @param $orderInfo
     * @param $operator
     * @return array
     * @throws \Exception
     */
    public function charging($orderInfo, $operator, $operatorId)
    {
        $customer = $this->db->where('c.id', $orderInfo['cust_id'])
            ->join('arch_customer_x x', 'x.id = c.id')
            ->get('arch_customer c')
            ->row_array();

        if (1 != $customer['cust_card_type_code']){
            throw new \Exception('不是使用身份证登记，不能使用医保结算');
        }
        $data['order'] = $orderInfo;

        $data['customer'] = [
            'cust_name' => $customer['cust_name'], //姓名
            'cust_sex' => 2 == $customer['cust_sex_code'] ? 'F' : 'M' , //性别
            'cust_birthday' => $customer['cust_birthday'], //出生日期
            'cust_id_card' => $customer['cust_id_card'], //身份证
            'cust_nation_code' => (int)$customer['cust_nation_code'], //民族
            'cust_mobile' => $customer['cust_mobile'], //家庭电话
            'cust_address' => $customer['cust_address'], //家庭地址
            'profession_code' => 90, //职业代码
            'province' => $customer['province_code'], //省代码
            'city' => $customer['city_code'], //地州代码
            'area' => $customer['district_code'], //区县代码

        ];  //职业代码  省代码 地州代码 县区代码  操作员

        $union = $this->db->where('order_id', $orderInfo['order_id'])->where('union_disc_flag', 0)->get('biz_order_package_union')->result_array();

        $unionCodes = array_column($union, 'union_code');

        $chargingUnion = $this->db->select('his_union_code,ipeis_union_code')->where_in('ipeis_union_code', $unionCodes)->where('sync_status', 1)->get('intf_union_relation')->result_array();
        $chargingUnionCodes = array_column($chargingUnion, 'his_union_code');
        log_message("debug","【his医生信息】".json_encode($unionCodes,320),__FUNCTION__);
        log_message("debug","【his医生信息】".json_encode($chargingUnionCodes,320),__FUNCTION__);
        $item = $this->db->select('d.his_dept_code,u.union_code,i.id,i.item_code,ui.qty,i.item_price,u.union_fee')
            ->join('dict_union_item ui', 'ui.union_id = u.id')
            ->join('dict_item i', 'i.id = ui.item_id')
            ->join('dict_exam_dept d', 'd.id = u.exam_dept_id', 'left')
            ->where_in('u.union_code', $chargingUnionCodes)
            ->where('u.status', 0)
//            ->where('u.exam_dept_id !=', 70)
            ->get('dict_union u')
            ->result_array();

        if (empty($item)){
            throw new \Exception('没有可用医保检查项');
        }
        $unionRelation = [];
        foreach ($chargingUnion as $vo){
            $unionRelation[$vo['his_union_code']] = $vo['ipeis_union_code'];
        }

        foreach ($item as &$v){
            $v['union_code'] = $unionRelation[$v['union_code']];
        }
        $data['union_item'] = $item;

//        //获取item
//        $hisDb = $this->config->item('his_db');
//        $link = oci_connect($hisDb['user'], $hisDb['password'], $hisDb['host'], 'UTF8');
//
//        //建档  $his_archives_no  门诊号
//        if (empty($customer['his_archives_no'])){
//            $putRecordRet = $this->sendPutRecord($link, $data);
//            $his_archives_no = $putRecordRet[0];
//            $this->db->update('arch_customer', ['his_archives_no' => $his_archives_no], ['id' => $orderInfo['cust_id']]);
//        }else{
//            $his_archives_no = $customer['his_archives_no'];
//        }
//        //挂号 $clinicCode  门诊流水号
//        $putRecordRet = $this->sendRegister($link, $data);
//
//        $clinicCode = $putRecordRet[0];
//
//        //医保划价
//        $hisCharging = $this->db->where('order_id', $orderInfo['order_id'])->where('status', 1)->get('intf_his_charging_data')->row_array();
//
//        $currentTime = (new \DateTime())->format('Y-m-d H:i:s');
//        if (empty($hisCharging)){
//            //新增
//            $recipeNo = $this->getRecipeNo($link);
//            $insertHisChargingData = [
//                'order_id' => $orderInfo['order_id'],
//                'recipe_no' => $recipeNo,
//                'created' => $currentTime,
//            ];
//            $this->db->insert('intf_his_charging_data', $insertHisChargingData);
//
//        }else{
//            $recipeNo = $hisCharging['recipe_no'];
//        }
//
//        $insertHisChargingItemData = [];
//        //查询没有的
//        $haveHisChargingItem = $this->db->where('order_id', $orderInfo['order_id'])
//            ->where('recipe_no', $recipeNo)
//            ->where_in('item_code', array_column($data['union_item'], 'item_code'))
//            ->order_by('item_no', 'asc')
//            ->get('intf_his_charging_item')
//            ->result_array();
//        if (!empty($haveHisChargingItem)){
//            $startItemNo = end($haveHisChargingItem)['item_no'] + 1;
//        }else{
//            $startItemNo = 1;
//        }
//        $haveHisChargingItemCode = array_column($haveHisChargingItem, 'item_code');
//        foreach ($data['union_item'] as $k=>$item){
//            if (empty($haveHisChargingItemCode) || !in_array($item['item_code'], $haveHisChargingItemCode)){
//                $sendChargingData = [
//                    'order_id' => $orderInfo['order_id'],
//                    'recipe_no' => $recipeNo,
//                    'item_no' => $startItemNo,
//                    'union_code' => $item['union_code'],
//                    'item_code' => $item['item_code'],
//                    'qty' => $item['qty'],
//                    'item_price' => $item['item_price'],
//                    'doctor' => $orderInfo['empl_code'] ?? 'admin',
//                    'dept_code' => $item['his_dept_code'],
//                    'created' => $currentTime,
//                ];
//                $insertHisChargingItemData[] = $sendChargingData;
//                $sendChargingData['his_archives_no'] = $his_archives_no;
//                $sendChargingData['clinicCode'] = $clinicCode;
//                $sendChargingData['operator'] = $operator;
//
//                $chargingRet = $this->sendCharging($link, $sendChargingData);
//                $startItemNo++;
//            }
//        }
//        if (!empty($insertHisChargingItemData)){
//            //发送
//            $this->db->insert_batch('intf_his_charging_item', $insertHisChargingItemData);
//        }
        $data['operator'] = $operator;
        $data['operator_id'] = $operatorId;
        $params['param'] = json_encode($data);

        StandapiClient::call_api_center('receive/charging', $params);

    }

    public function charging_back($params)
    {
        log_message("debug","【his医生信息】".json_encode($params,320),__FUNCTION__);
        $this->db->trans_begin();
        try {
            $service = $this->parse_service('costmgr_v2\CostmgrService');
            // 4、保存流水
            $payChannelCode = 'UPC005';
            $orderId = $params['order_id'];
            $this->load->library('user/util');
            $outTradeNo = $this->util->manager_flow_num(PAY_CODE, $orderId, $payChannelCode);
            $tradeBatchNum = $this->util->trade_batch_num($orderId);
            $userId = $params['operator_id'];

            // 5、保存快照
            $bizOrderPackageUnion = BizOrderPackageUnionModel::getInstance()->get_all_by_paras(['order_id' => $orderId, 'union_code' => $params['union_code']]);
            $saveForFinOrderUnionData = [];
            foreach ($bizOrderPackageUnion as $item) {
                $saveForFinOrderUnionData[] = [
                    'order_pkg_union_id' => $item['id'],
                    'order_id' => $item['order_id'],
                    'union_id' => $item['union_id'],
                    'union_code' => $item['union_code'],
                    'union_name' => $item['union_name'],
                    'union_fee' => $item['union_fee'],
                    'disc_type' => $item['disc_type'],
                    'disc_id' => $item['disc_id'],
                    'disc_rate' => $item['disc_rate'],
                    'disc_price' => $item['disc_price'],
                    'order_union_option' => $item['order_union_option'],
                    'pay_flag' => $item['pay_flag'],
                    'charge_status_id' => null,
                    'charge_status_code' => null,
                    'union_exam_flag' => $item['union_exam_flag'],
                    'order_union_fee_flag' => $item['order_union_fee_flag'],
                    'order_union_set_flag' => $item['order_union_set_flag'],
                    'status' => $item['status'],
                    'order_by' => $item['order_by'],
                    'delete_flag' => $item['delete_flag'],
                    'created' => date('Y-m-d H:i:s'),
                    'created_by' => $userId,
                    'updated' => date('Y-m-d H:i:s'),
                    'updated_by' => $userId,
                    'union_subjoin_flag' => $item['union_subjoin_flag'],
                    'union_subjoin_id' => $item['union_subjoin_id'],
                    'union_pay_id' => $item['union_pay_id'],
                    'union_pay_code' => $item['union_pay_code'],
                    'union_pay_time' => $item['union_pay_time'],
                    'union_pay_fee' => $item['union_pay_fee'],
                    'deduced_flag' => $item['order_union_ded_flag'],  // 标识项目是否被抵扣，0否，1是，默认是0
                    'trade_batch_num' => $tradeBatchNum,
                    'his_flow_num' => null,     // his单据号
                    'pay_type' => null,         // 支付方式编码
                    'pay_typename' => null,     // 支付方式名称
                    'union_request_no' => null, // 申请单号
                ];
            }
            // 批量插入 fin_order_union
            $ret= FinOrderUnionModel::getInstance()->db->insert_batch(FinOrderUnionModel::table_name(), $saveForFinOrderUnionData);

            $fee = array_sum(array_column($bizOrderPackageUnion, 'union_fee'));
            // 6、付款流水
            $recordInfo = $service->recordFinOrderFlow($orderId, $fee, $payChannelCode, $userId, $outTradeNo, $tradeBatchNum);

            LogHelper::log('生成流水返回：' . json_encode_unicode($recordInfo));

//            // 7、调用平台内付款逻辑：体检卡支付、优惠券、
//            $payment = null;
//
//            // 8、调用各自的付款逻辑
//            if ($payment instanceof PaymentInterface) {
//                // 8.1、发起付款
//                $result = $payment->pay($fee);
//                // 8.2、保存付款结果
//                FinOrderV2FlowPaymentModel::getInstance()->save([
//                    'id' => $recordInfo['fin_order_v2_flow_payment_id'],
//                    'result' => json_encode_unicode($result)
//                ], FinOrderV2FlowPaymentModel::table_name(), $this->admin['id']);
//            }

            // 9、如果付款完成，回去改组合和耗材的 pay_flag

            // 9.1、变动批次号
            $tradeModifyNum = uniqid(false, false);

            $unionSaveData = [];
            foreach ($bizOrderPackageUnion as $union) {
                $unionSaveData[] = [
                    'trade_modify_num' => $tradeModifyNum,
                    'biz_order_package_union_id' => $union['id'],
                    'type' => FinOrderV2FlowUnionModifyLogModel::TYPE_PAY,
                    'modify_fee' => $union['disc_price'],
                    'created' => date('Y-m-d H:i:s'),
                    'created_by' => $userId
                ];
            }

            if (!empty($unionSaveData)) {
                // fin_order_v2_flow_union_modify_log
                LogHelper::log(__FUNCTION__ . ' trade_modify_num：' . $tradeModifyNum . '，将项目从未支付改成已支付：' . json_encode_unicode($unionSaveData));
                FinOrderV2FlowUnionModifyLogModel::getInstance()->db->insert_batch(FinOrderV2FlowUnionModifyLogModel::table_name(), $unionSaveData);
            }

//            $service->savePayCostModifyLog($orderId, $userId, $tradeModifyNum);   // 保存将从未支付 => 已支付 的日志
            $service->saveTradeModifyNumToFlow($orderId, $userId, $tradeModifyNum);    // 把变动号保存给流水

//            $service->changeGiveUpToUnPay($orderId, $userId);      // 修改支付状态从 未支付 => 已支付

            LogHelper::log('管理员：' . $userId . '修改 order_id=' . $orderId . ' 弃项为待支付');
            // 10、发送操作申请
            $this->util->save_order_deal_param(['order_id' => $orderId]);
            $service->changeToPaid($orderId, $userId, $params['union_code']);
            $this->db->trans_commit();
            return true;
        } catch (\RuntimeException $exception) {
            log_message("debug","【his医生信息】".$exception->getMessage(),__FUNCTION__);
            $this->db->trans_rollback();
            LogHelper::log('财务订单下单接口异常：' . $exception->getMessage(), 'error');
            return ["error" => $exception->getMessage()];
        }
    }

    /**
     * DESC: 建档
     * name: Lemon
     * @param $link
     * @param $params
     * @return array
     * @throws \Exception
     */
    private function sendPutRecord($link, $params)
    {
        $putRecordSql = "BEGIN 
        XKTJ_PEIS_RECORD(
        '{$params['customer']['cust_name']}',
        '{$params['customer']['cust_sex']}',
        TO_DATE('{$params['customer']['cust_birthday']}','YYYY-MM-DD HH24:MI:SS'),
        '01',
        '{$params['customer']['cust_id_card']}',
        '{$params['customer']['cust_nation_code']}',
        '{$params['customer']['cust_mobile']}',
        '{$params['customer']['profession_code']}',
        '{$params['customer']['province']}',
        '{$params['customer']['city']}',
        '{$params['customer']['area']}',
        '{$params['customer']['cust_address']}',
        '{$params['operator']}',
        :ret
        );
        END;";
        $putRecordRet = '';
        $ret = $this->oracleQuery($link, $putRecordSql, ':ret', $putRecordRet, 200);
        if (false === $ret){
            throw new \Exception('建档失败');
        }
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
     * @param $params
     * @return array
     * @throws \Exception
     */
    private function sendRegister($link, $params)
    {
        $registerSql = "BEGIN 
        XKTJ_PEIS_REGISTER(
        '01',
        '{$params['customer']['cust_id_card']}',
        '{$params['customer']['cust_name']}',
        '{$params['operator']}',
        :ret
        );
        END;";
        $registerRet = '';
        $ret = $this->oracleQuery($link, $registerSql, ':ret', $registerRet, 200);
        if (false === $ret){
            throw new \Exception('挂号失败');
        }
        $registerRet = explode('|',$registerRet);
        if (1 != $registerRet[0]){
            throw new \Exception($registerRet[1]);
        }
        return explode('^', $registerRet[1]);
    }

    private function sendCharging($link, $params)
    {
        $chargingSql = "BEGIN 
        XKTJ_PEIS_CHARGING(
        '{$params['his_archives_no']}',
        '{$params['clinicCode']}',
        '{$params['recipe_no']}',
        '{$params['item_no']}',
        '{$params['item_code']}',
        '{$params['qty']}',
        '{$params['dept_code']}',
        '{$params['doctor']}',
        '1014',
        '{$params['operator']}',
        :ret
        );
        END;";
        $chargingRet = '';
        $ret = $this->oracleQuery($link, $chargingSql, ':ret', $chargingRet, 200);
        if (false === $ret){
            throw new \Exception('划价失败');
        }
        $chargingRet = explode('|',$chargingRet);
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
            log_message("debug","oracle执行错误". $this->oracle_error($r)."---sql为--".$sql,'manager');
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
            log_message("debug","oracle执行错误". $this->oracle_error($r)."---sql为--".$sql,'manager');
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

    /**
     * DESC: 同步his省份
     * name: Lemon
     * @throws \Exception
     */
    public function syncHisProvence()
    {
        $res = StandapiClient::call_api_center('receive/sync_his_provence', []);
//        log_message("debug","【his省份信息】".json_encode($res,320),__FUNCTION__);
        if (!empty($res)){
            $classify_id = 115;
            $classify_code = '0115';
            $start_time = microtime(true);
            $orderByMax = $this->db->select_max('order_by')
                ->where('classify_id', $classify_id)
                ->where('classify_code', $classify_code)
                ->get('com_classify_item')
                ->row_array();

            $startOrder = ($orderByMax['order_by'] ?? 0) + 1;

            $currentTime = (new \DateTime())->format('Y-m-d H:i:s');
            foreach ($res as $val){
                $res_result = $this->db->select('id',false)
                    ->where('classify_id', $classify_id)
                    ->where('classify_code', $classify_code)
                    ->where('item_code', $val['CODE'])
                    ->get('com_classify_item')
                    ->row_array();
                if (empty($res_result)){
                    //新增
                    $insert_data = [
                        'classify_id' => $classify_id,
                        'classify_code' => $classify_code,
                        'item_code' => $val['CODE'],
                        'item_name' => $val['NAME'],
                        'order_by' => $startOrder,
                        'created' => $currentTime
                    ];
                    //不存在---新增
                    $this->db->insert('com_classify_item', $insert_data);
                    $startOrder++;
                }else{
                    //修改
                    $update_data = [
                        'item_name' => $val['NAME'],
                        'updated' => $currentTime,
                    ];

                    $this->db->update('com_classify_item', $update_data, ['classify_id' => $classify_id, 'classify_code' => $classify_code,'item_code' => $val['CODE']]);
                }
            }
            $end_time = microtime(true);
            log_message("debug","his省份同步完成 耗时---".round($end_time-$start_time,3)."---秒,当前服务器时间为---".date("Y-m-d H:i:s",time()),__FUNCTION__);

        }
    }

    /**
     * DESC: 同步his城市
     * name: Lemon
     * @throws \Exception
     */
    public function syncHisCity()
    {
        $res = StandapiClient::call_api_center('receive/sync_his_city', []);
        if (!empty($res)){
            $classify_id = 116;
            $classify_code = '0116';
            $start_time = microtime(true);
            $orderByMax = $this->db->select_max('order_by')
                ->where('classify_id', $classify_id)
                ->where('classify_code', $classify_code)
                ->get('com_classify_item')
                ->row_array();

            $startOrder = ($orderByMax['order_by'] ?? 0) + 1;

            //获取上级code数据
            $parCodeArr = $this->db->select('item_code')
                ->where('classify_id', 115)
                ->where('classify_code', '0115')
                ->where('delete_flag', 0)
                ->get('com_classify_item')
                ->result_array();
            if (empty($parCodeArr)){
                return;
            }
            $parCodeArr = array_column($parCodeArr, 'item_code');

            $currentTime = (new \DateTime())->format('Y-m-d H:i:s');
            foreach ($res as $val){
                if (!in_array($val['MARK'], $parCodeArr)){
                    continue;
                }
                $res_result = $this->db->select('id',false)
                    ->where('classify_id', $classify_id)
                    ->where('classify_code', $classify_code)
                    ->where('item_code', $val['CODE'])
                    ->get('com_classify_item')
                    ->row_array();
                if (empty($res_result)){
                    //新增
                    $insert_data = [
                        'classify_id' => $classify_id,
                        'classify_code' => $classify_code,
                        'item_code' => $val['CODE'],
                        'item_name' => $val['NAME'],
                        'par_item_code' => $val['MARK'],
                        'par_classify_code' => '0115',
                        'order_by' => $startOrder,
                        'created' => $currentTime
                    ];
                    //不存在---新增
                    $this->db->insert('com_classify_item', $insert_data);
                    $startOrder++;
                }else{
                    //修改
                    $update_data = [
                        'item_name' => $val['NAME'],
                        'par_item_code' => $val['MARK'],
                        'updated' => $currentTime,
                    ];

                    $this->db->update('com_classify_item', $update_data, ['classify_id' => $classify_id, 'classify_code' => $classify_code,'item_code' => $val['CODE']]);
                }
            }
            $end_time = microtime(true);
            log_message("debug","his城市同步完成 耗时---".round($end_time-$start_time,3)."---秒,当前服务器时间为---".date("Y-m-d H:i:s",time()),__FUNCTION__);
        }
    }

    /**
     * DESC: 同步his区县
     * name: Lemon
     * @throws \Exception
     */
    public function syncHisArea()
    {
        $res = StandapiClient::call_api_center('receive/sync_his_area', []);
        if (!empty($res)){
            $classify_id = 117;
            $classify_code = '0117';
            $start_time = microtime(true);
            $orderByMax = $this->db->select_max('order_by')
                ->where('classify_id', $classify_id)
                ->where('classify_code', $classify_code)
                ->get('com_classify_item')
                ->row_array();

            $startOrder = ($orderByMax['order_by'] ?? 0) + 1;

            //获取上级code数据
            $parCodeArr = $this->db->select('item_code')
                ->where('classify_id', 116)
                ->where('classify_code', '0116')
                ->where('delete_flag', 0)
                ->get('com_classify_item')
                ->result_array();
            if (empty($parCodeArr)){
                return;
            }
            $parCodeArr = array_column($parCodeArr, 'item_code');

            $currentTime = (new \DateTime())->format('Y-m-d H:i:s');
            foreach ($res as $val){
                if (!in_array($val['MARK'], $parCodeArr)){
                    continue;
                }
                $res_result = $this->db->select('id',false)
                    ->where('classify_id', $classify_id)
                    ->where('classify_code', $classify_code)
                    ->where('item_code', $val['CODE'])
                    ->get('com_classify_item')
                    ->row_array();
                if (empty($res_result)){
                    //新增
                    $insert_data = [
                        'classify_id' => $classify_id,
                        'classify_code' => $classify_code,
                        'item_code' => $val['CODE'],
                        'item_name' => $val['NAME'],
                        'par_item_code' => $val['MARK'],
                        'par_classify_code' => '0116',
                        'order_by' => $startOrder,
                        'created' => $currentTime
                    ];
                    //不存在---新增
                    $this->db->insert('com_classify_item', $insert_data);
                    $startOrder++;
                }else{
                    //修改
                    $update_data = [
                        'item_name' => $val['NAME'],
                        'par_item_code' => $val['MARK'],
                        'updated' => $currentTime,
                    ];

                    $this->db->update('com_classify_item', $update_data, ['classify_id' => $classify_id, 'classify_code' => $classify_code,'item_code' => $val['CODE']]);
                }
            }
            $end_time = microtime(true);
            log_message("debug","his区县同步完成 耗时---".round($end_time-$start_time,3)."---秒,当前服务器时间为---".date("Y-m-d H:i:s",time()),__FUNCTION__);
        }
    }
}