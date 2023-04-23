<?php

namespace services\thirdpart;

use CI_Config;
use Exception;
use extend\enums\ParameterEnum;
use extend\enums\ThirdPartEnums;
use extend\Singleton;
use models\biz\BizOrderModel;
use models\biz\OrderPackageUnionModel;
use models\classify\ClassifyItemModel;
use models\intf\IntfAbutmentDetailModel;
use models\intf\IntfSendDetailModel;
use models\intf\IntfStatusDetailModel;
use models\priv\ParameterModel;
use services\BaseService;
use models\fin\InvoiceModel;
use models\arch\CustomerModel;
use models\dict\ApplyUnionModel;
use models\intf\IntfCustomerModel;
use models\standapi\StandApiModel;
use models\intf\IntfOrderUnionModel;
use extend\common\lib\StandapiClient;
use extend\enums\StandapiEnum;
use models\biz\BizOrderPackageUnionModel;
use services\order\OrderBatchRegisterService;

/**
 *
 * His系统交互类
 * Class HisService
 * @property   CI_Config config
 * @package services\thirdpart
 *
 */
class HisService extends BaseService
{
    use Singleton;

    //申请科室
    public $apply_dept = [
        'apply_dept_code' => '2009',
        'apply_dept_name' => '体检科',
    ];

    function __construct()
    {
        parent::__construct();
        if (ParameterModel::getApiConf()['use_token'] ?? false) {
            StandapiClient::_checkLogin();
        }
       $this->apply_dept =  ParameterModel::getByParamCodeJson(ParameterEnum::IPEIS_CODE) ?? $this->apply_dept;
    }

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
                //

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
                    // 注释 不更新科室
//                    'exam_dept_id' => $dsBaseDict['exam_dept_id'],
//                    'exam_dept_code' => $dsBaseDict['exam_dept_code'],
//                    'work_group_id' => $dsBaseDict['work_group_id'],
//                    'work_group_code' => $dsBaseDict['work_group_code'],
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
     * 核对档案信息
     * @param mixed $cust_info
     * @return array|mixed|string
     * @throws Exception
     */
    private function checkArchive($cust_info)
    {
        $applySendConf = ParameterModel::getApiConf();
        if(!$applySendConf["archQuery"] ?? false) {
            return false;
        }

        $this->validaArchiveData($cust_info);
        $res = self::call_api(StandapiEnum::ARCH_QUERY, $cust_info);
        IntfSendDetailModel::getInstance()->saveSendLog($res,'查询his档案',$cust_info);
        if (!$res['status'] || !isset($res['data']['medical_record_no']) || !$res['data']['medical_record_no']) {
            return false;
        }
        return $res['data']['medical_record_no'];
    }


    /**
     * 创建档案信息
     * @param $data
     * @throws Exception
     * @return array|mixed
     */
    private function createArchive($data)
    {
//        $cust_info = [
//            'cust_name' => $data['cust_name'] ?? "",
//            'cust_sex_code' => $data['cust_sex_code'] ?? "",
//            'cust_card_type_code' => $data['cust_card_type_code'] ?? '',
//            'cust_id_card' => $data['cust_id_card'] ?? '',
//            'cust_mobile' => $data['cust_mobile'] ?? '',
//        ];
//        $this->validaArchiveData($cust_info);
        $res = StandapiClient::call_api_center('receive/create_patient', $data);
        if( count($res) == 0 ) {
            throw new Exception("建档请求失败");
        }
        return  $res;
    }

    /**
     * 校验档案数据
     * @param $data
     * @throws Exception
     */
    private function validaArchiveData($data){
        $valida = ['cust_name','cust_id_card'];
        foreach ($valida as $v) {
            if(!isset($data[$v]) || !$data[$v]) {
                throw new Exception("参数".$v."错误");
            }
        }
    }


    /**
     * HIS修改用户档案
     * @param $data
     * @throws Exception
     * @return array|bool|mixed
     */
    public function update_cust_his_info($data)
    {
        $archUpdate = ParameterModel::getApiConf()['archUpdate'] ?? false;//是否发送建档修改
        if ($archUpdate) { //发送建档申请
            if(!isset($data['cust_id']) || !$data['cust_id']) {
                throw new Exception("参数cust_id错误");
            }
            $intf_cust_info = IntfCustomerModel::getInstance()->get_info(IntfCustomerModel::table_name(),['cust_id'=>$data['cust_id']],"medical_record_no",true);
            if(!$intf_cust_info || !$intf_cust_info['medical_record_no']) {
                throw new Exception("没有该用户的档案");
            }
            $data['medical_record_no'] = $intf_cust_info['medical_record_no'];
            $res = self::call_api(StandapiEnum::ARCH_UPDATE, $data);
            IntfSendDetailModel::getInstance()->saveSendLog($res,'修改his档案',$data);
        }
        return true;
    }

    /**
     * 患者建档
     * @param $create_patient_info
     * @return bool|int|mixed
     * @throws Exception
     */
    public function patientCreateArchive($create_patient_info)
    {

        log_message("debug",'创建档案：'.json_encode_unicode($create_patient_info),"patient_archive");
        $cust_id = $create_patient_info['cust_id']??'';
        $order_id = $create_patient_info['order_id']??'';
        $user_id = $create_patient_info['user_id']??'';
        $user_name = $create_patient_info['user_name']??'';
        $user_emp_num = $create_patient_info['user_emp_num']??'';
        $user_logon_id = $create_patient_info['user_logon_id']??'';
        $intf_customer_model = IntfCustomerModel::getInstance();
        $model = CustomerModel::getInstance();
        if(!$cust_id) {
            throw new Exception('档案创建，cust_id缺失');
        }
        $cust_info = $intf_customer_model->get_medical_by_id(['id' => $cust_id]);

        $data_type = 1;  //1:体检系统生成的档案号  2：HIS回传的档案号
        $tj_medical = $his_medical = '';
        if ($cust_info && isset($cust_info['medical_record_no']) && $cust_info['medical_record_no']) {
            //根据data_type 判断medical是哪个系统生成并保存的
            $cust_info['data_type'] == 1 ? $tj_medical=$cust_info['medical_record_no']:$his_medical=$cust_info['medical_record_no'];
        }
        $biz_order_model = BizOrderModel::getInstance();
        $company_info = $biz_order_model->get_order_customer_info_by_order_id($order_id, false);
        $archCreate = config_item('archCreate');
        $clinic_id = '';
        if ($archCreate) {
            if ($his_medical) return $his_medical;
            //$medical_record_no = $this->checkArchive($cust_info);
            //if (!$medical_record_no) {
//                $cust_info['cust_id'] = $cust_id;
//                $cust_info['operator_id'] = $user_id;
//                $cust_info['operator_name'] = $user_name;
//                $cust_info['operator_code'] = $user_emp_num;
            $cust_info = [
                'cust_name' => $cust_info['cust_name'], //姓名
                'cmp_org_code' => $company_info['cmp_org_code'], //社会信用代码
                'order_cmp_name' => $company_info['order_cmp_name'], //公司名称
                'cust_sex' => 2 == $cust_info['cust_sex_code'] ? 'F' : 'M' , //性别
                'cust_birthday' => $cust_info['cust_birthday'], //出生日期
                'cust_id_card' => $cust_info['cust_id_card'], //身份证
                'cust_card_type_code' => $cust_info['cust_card_type_code'], //证件类型code 1 身份证 2 护照 3回乡证 4台胞证 5其他
                'cust_nation_code' => (int)$cust_info['cust_nation_code'], //民族
                'cust_mobile' => $cust_info['cust_mobile'], //家庭电话
                'cust_address' => $cust_info['cust_address'], //家庭地址
                'profession_code' => 90, //职业代码
                'province' => $cust_info['province_code'], //省代码
                'operator_id' => $user_id,
                'operator_name' => $user_name,
                'operator_code' => $user_logon_id,
                'city' => $cust_info['city_code'], //地州代码
                'area' => $cust_info['district_code'], //区县代码

            ];
            log_message("debug","【hiswewerwrwrwr医生信息】","23434343434");
            $res = $this->createArchive($cust_info);
            $medical_record_no = $res[0];
            $clinic_id = $res['clinic_id']??'';
            //}
            $data_type = 2;
        }else{
            if($tj_medical) return $tj_medical;
            $tmp_mic = explode(' ', microtime()); //默认4位毫秒
            $medical_record_no = date("YmdHis").str_pad(round($tmp_mic[0]*10000,0),4,'0',STR_PAD_LEFT);
        }
        log_message("debug",'进入保存档案号：'.$cust_id."-".$medical_record_no,'patient_archive');
        $model->save_intf_customer($cust_id, $medical_record_no, $user_id,$clinic_id,$data_type);

        return $medical_record_no;
    }

    /**
     * bs主动发送检验检查申请或撤销申请(通用)
     */
    public function send_common_apply_data()
    {
        $this->load->library('user/util');
        $n = 5;
        $param_arr = [];
        for ($i = 1; $i < $n; $i++) {
            if ($param_tmp = $this->util->get_order_deal_param()) {//单个登记队列
                $param_tmp = json_decode($param_tmp, true);
            // if($param_tmp = $this->util->get_order_deal_param() || true){//单个登记队列
            //     $param_tmp = json_decode('{"order_id": 504}', true);
                $param_tmp['queue_type'] = 0;
                $param_arr[$param_tmp['order_id']] = $param_tmp;
            } else {
                if ($param_tmp = $this->util->get_group_order_deal_param()) {//批量登记队列
                    $param_tmp = json_decode($param_tmp, true);
                    $param_tmp['queue_type'] = 1;
                    $param_arr[$param_tmp['order_id']] = $param_tmp;
                }
            }
        }
        log_msg( '获取缓存：$params==' . json_encode_unicode($param_arr));
        try {
            foreach ($param_arr as $params) {
                $this->send_common($params, $params['queue_type']);
            }
        } catch (\Exception $e) {
            log_msg('发送任务异常：' . $e->getMessage(), 'error');
        }
    }

    public function send_common($params, $queue_type)
    {
        try {
            if (isset($params['order_id']) && $params['order_id']) {
                $flag = $this->util->lock_send_task($params['order_id']);
                if (!$flag) {
                    if ($queue_type) {
                        $this->util->save_group_order_deal_param($params);
                    } else {
                        $this->util->save_order_deal_param($params);
                    }
                    log_msg('上一个定时任务执行还在执行中:' . $params['order_id']);
                    return;
                }
            } else {
                return;
            }

            #region 处理可合管项目的重新发送
            $apiConf = \models\priv\ParameterModel::getApiConf();
            if ($apiConf['merge_reapply']) $this->handle_merge_reapply($params, $queue_type);
            #endregion

            //获取未发送或需要撤销组合
            $standApiModel = StandApiModel::getInstance();
            $order_info = $standApiModel->getOrderInfo($params['order_id']);
            if (!$order_info) {
                $this->util->unlock_send_task($params['order_id']);
                log_msg( $params['order_id'].'param==>查找不到订单信息');
                return;
            }

            if ($apiConf['his_register'] && empty($order_info['his_register_num'])) {
                $this->util->unlock_send_task($params['order_id']);
                log_msg( $params['order_id'].'param==>订单没有his登记');
                return;
            }

            $apply_dept = $this->apply_dept;
            $order_info['apply_dept_code'] = $apply_dept['apply_dept_code'];//申请科室
            $order_info['apply_dept_name'] = $apply_dept['apply_dept_name'];//申请科室

            $rst = $standApiModel->getUnsendUnion($params);

            log_msg( 'param==>' . json_encode_unicode($rst));

            $send_lis = []; //发送
            $cancel_send_lis = [];//撤销

            //LIS撤销申请类型，0：通过条码撤销，1：通过申请单号撤销
            $lis_cancel_type = $apiConf['lis_cancel_type'] ?? 0;
            //LIS申请类型，0：全部一起申请，1：根据条码号申请
            $lis_send_type = $apiConf['lis_send_type'] ?? 1;
            //检查需要一起发送申请科室，LWUS,LWRIS等
            $together_send_dept_code = $apiConf['together_send_dept_code'] ?? '';
            $together_send_check = [];
            if (!empty($rst)) {

                foreach ($rst as $item) {
                    if ($item['dept_code'] == ThirdPartEnums::LIS) {
                        if ($item['apply_type'] == StandApiModel::JYJC_NW) {
                            if ($lis_send_type) {
                                $send_lis[$item['union_barcode']][] = $item;
                            } else {
                                $send_lis[] = $item;
                            }
                        } else {
                            if (isset($cancel_send_lis[$item['union_barcode']])) {//条码是否已发送过撤销申请
                                continue;
                            }

                            if (!$lis_cancel_type) {
                                $cancel_send_lis[$item['union_barcode']] = $item;
                                $this->send_lis(array($item), $order_info, $item['apply_type'], $lis_cancel_type);
                            } else {
                                $this->send_lis(array($item), $order_info, $item['apply_type'], $lis_cancel_type);
                            }
                        }
                    } else {
                        $together_send_dept_code = $together_send_dept_code ? explode(',', $together_send_dept_code) : [];
                        if ($together_send_dept_code && in_array($item['dept_code'], $together_send_dept_code) && $item['apply_type'] == StandApiModel::JYJC_NW) {
                            $together_send_check[] = $item;
                        } else {
                            $this->send_check(array($item),$order_info);
                        }
                    }
                }

                if ($lis_send_type) {
                    foreach ($send_lis as $send_lis_item) {
                        $this->send_lis($send_lis_item,$order_info);
                    }
                } else {
                    if ($send_lis) {
                        $this->send_lis($send_lis,$order_info);
                    }
                }
                //发送检查申请
                if ($together_send_check) {
                    $this->send_check($together_send_check,$order_info);
                }
            } else {
                log_msg( '发送数据获取为空' . json_encode_unicode($rst));
            }
            $flag = $this->util->unlock_send_task($params['order_id']);
            if (!$flag) {
                log_msg('锁解发送任务锁失败', 'error');
            }
        } catch (\Exception $e) {
            log_msg('发送失败:' . $e->getMessage(), 'error');
//            if ($queue_type) {
//                $this->util->save_group_order_deal_param($params);
//            } else {
//                $this->util->save_order_deal_param($params);
//            }
            $flag = $this->util->unlock_send_task($params['order_id']);
            if (!$flag) {
                log_msg('锁解发送任务锁失败','error');
            }
            // throw new \Exception($e->getTraceAsString());
        }

    }

    /**
     * @description
     * 重新加入队列
     *
     * @param array $params
     * @param string $queue_type
     * @return void
     * @since 2021-06-25
     */
    public function push_list($params, $queue_type)
    {
        if ($queue_type) {
            $this->util->save_group_order_deal_param($params);
        } else {
            $this->util->save_order_deal_param($params); //报存订单队列
        }
    }

    /**
     * @description
     * 处理合并申请单重新发送
     * A、B项目可合并，A项目已发送且未执行，如有B项目加项，则发送A项目的撤销申请，同时重置A、B项目的发送状态、条码号，将其重新放入任务队列，合并重发
     * 根据配置 apply_allow_update 可以合并的，根据execute_status区分是否已发送。其余的根据 apply_id + union_barcode + execute_status 判断
     *
     * @param array $params
     * @param string $queue_type
     * @return void
     * @since 2021-06-25
     */
    public function handle_merge_reapply($params, $queue_type)
    {
        // 未执行状态 申请单ID相同 条码不相同
        $unexe_unions = $this->get_unexe_union($params['order_id']);
        if (!$unexe_unions) return;

        $applyCfg = \models\priv\ParameterModel::getByParamCodeJson(\extend\enums\ParameterEnum::APPLY_CFG);
        if (isset($applyCfg['apply_allow_update']) && $applyCfg['apply_allow_update']) {
            // 合并申请单
            $barcode_unions = [];
            foreach ($unexe_unions as $union) {
                if (isset($barcode_unions[$union['union_barcode']])) {
                    array_push($barcode_unions[$union['union_barcode']], $union);
                    continue;
                }
                $barcode_unions[$union['union_barcode']][] = $union;
            }

            foreach ($barcode_unions as $barcode => $unions) {
                $execute_status = array_unique(array_column($unions, 'execute_status'));
                if (count($execute_status) != 2) continue;
                // 已发送申请单，但是又合并的
                $this->db->where('order_id', $params['order_id']);
                $this->db->where('union_barcode', $barcode);
                $this->db->where_in('execute_status', 1);
                $this->db->update(IntfOrderUnionModel::table_name(), ['is_cancel' => 1, 'updated' => date('Y-m-d H:i:s')]);
                log_msg( '[待撤销申请]' . $this->db->last_query());
                $this->db->where('order_id', $params['order_id']);
                $this->db->where('union_barcode', $barcode);
                $this->db->where_in('execute_status', 0);
                $this->db->update(IntfOrderUnionModel::table_name(), ['delete_flag' => 1, 'updated' => date('Y-m-d H:i:s')]);
                log_msg( '[待删除申请]' . $this->db->last_query());
                // 重新加入队列
                $this->push_list($params, $queue_type);
            }
        } else {
            // 不合并申请单需要判断
            $barcode_applyid = array_column($unexe_unions, 'apply_id', 'union_barcode');
            $apply_list = [];
            foreach ($barcode_applyid as $union_barcode => $apply_id) {
                // 判断申请单号是否重复
                if (!in_array($apply_id, $apply_list)) {
                    array_push($apply_list, $apply_id);
                    continue;
                }

                // 重复的申请单号，则将该申请单下的组合更新为已取消  发送撤销申请
                $cancel_union_apply = [];
                $del_union = [];
                foreach ($unexe_unions as $union) {
                    if ($union['apply_id'] == $apply_id && $union['execute_status'] == 1) array_push($cancel_union_apply, $union['order_pkg_union_id']);
                    if ($union['apply_id'] == $apply_id && $union['execute_status'] == 0) array_push($del_union, $union['order_pkg_union_id']);
                }
                if ($cancel_union_apply) {
                    $this->db->where('order_id', $params['order_id']);
                    $this->db->where_in('order_pkg_union_id', $cancel_union_apply);
                    $this->db->update(IntfOrderUnionModel::table_name(), ['is_cancel' => 1, 'updated' => date('Y-m-d H:i:s')]);
                    log_msg( '[待撤销申请]' . $this->db->last_query());
                    $this->db->where('order_id', $params['order_id']);
                    $this->db->where_in('order_pkg_union_id', $del_union);
                    $this->db->update(IntfOrderUnionModel::table_name(), ['delete_flag' => 1, 'updated' => date('Y-m-d H:i:s')]);
                    log_msg( '[待删除申请]' . $this->db->last_query());
                    $this->db->where('order_id', $params['order_id']);
                    $this->db->where_in('id', array_merge($del_union, $cancel_union_apply));
                    $this->db->update(BizOrderPackageUnionModel::table_name(), ['union_barcode' => null, 'updated' => date('Y-m-d H:i:s')]);
                    log_msg( '[重置组合表的条码号]' . $this->db->last_query());
                    // 重新加入队列
                    $this->push_list($params, $queue_type);
                }
            }
        }
    }

    /**
     * bs主动发送lis申请
     * @param $data
     * @param string $type
     * @param int $lis_cancel_type
     *
     * @throws \Exception
     */
    public function send_lis($data, $order_info ,$type = StandApiModel::JYJC_NW, $lis_cancel_type = 0)
    {
        $barcodeConf = \models\priv\ParameterModel::getByParamCodeJson(\extend\enums\ParameterEnum::BARCODE_CONF);
        //检验科是否本地生成条码
        $barcode_make = $barcodeConf['barcode_make'];
        log_msg( json_encode($data));
        $param = ['type' => $type,'list' => json_encode_unicode($data),'order_info' => json_encode_unicode($order_info)];
        $rs = $this->call_api(StandapiEnum::LIS_APPLY, $param);

        if ($rs['status']) {
            $rst = StandApiModel::getInstance()->updateJYJCSendStatus($data,$type,$lis_cancel_type);
            if (!$rst) {
                log_msg('检验发送状态更新失败' . json_encode_unicode($data), 'error');
            }
        } else {
            log_msg('检验发送失败' . json_encode_unicode($rs), 'error');
        }
        $type = $data[0]['apply_type'] ==  StandApiModel::JYJC_NW ? '申请' : '撤销';
        IntfSendDetailModel::getInstance()->saveSendLog($rs,'检验'.$type,$param);
    }

    /**
     * bs主动发送检查申请
     * @param $data
     * @param $order_info
     * @return int
     */
    public function send_check($data,$order_info)
    {
        $type = $data[0]['apply_type'] ==  StandApiModel::JYJC_NW ?  StandApiModel::JYJC_NW : StandApiModel::JYJC_CA;

        $param = ['type' => $type,'list' => json_encode_unicode($data),'order_info' => json_encode_unicode($order_info)];
        $rs = $this->call_api(StandapiEnum::PACS_APPLY, $param);

        if ($rs['status']) {
            $rst = StandApiModel::getInstance()->updateJYJCSendStatus($data,$type);
            if (!$rst['status']) {
                log_msg('检查发送状态更新失败' . json_encode_unicode($data), 'error');
            }
        } else {
            log_msg('检查发送失败' . json_encode_unicode($rs), 'error');
        }
        $type = $type ==  StandApiModel::JYJC_NW ? '申请' : '撤销';
        IntfSendDetailModel::getInstance()->saveSendLog($rs,'检查'.$type,$param);
    }

    /**
     * @description 订单登记
     *
     * @since 2022-05-11
     * @param array $cust_info
     * @return mixed
     */
    public function checkRegister($cust_info)
    {
        //发送创建就诊号
        $his_register = $this->db->select('order_id,his_register_num')
            ->from('intf_order')
            ->where('order_id', $cust_info['order_id'])
            ->where('status = 0 and delete_flag = 0')
            ->get()->row_array();
        if (isset($his_register['his_register_num']) && $his_register['his_register_num']) {
            log_msg( $cust_info['order_id'] . '已挂号,挂号号码：' . $his_register['his_register_num']);
            return ['status' => true];
        }
        $intf_customer =  IntfCustomerModel::getInstance()->get_one_by_cust_id($cust_info['cust_id']);
        $cust_info['medical_record_no'] = $intf_customer['medical_record_no'] ?? '';

        $applyDept = $this->apply_dept;
        $cust_info['apply_dept_code'] = $applyDept['apply_dept_code'];//申请科室
        $cust_info['apply_dept_name'] = $applyDept['apply_dept_name'];//申请科室
        $rs = $this->call_api(StandapiEnum::ORDER_REGISTER, $cust_info);

        IntfSendDetailModel::getInstance()->saveSendLog($rs,'HIS挂号登记',$cust_info);

        log_msg( 'checkRegister,发送创建就诊号=' . json_encode($rs));
        if (!isset($rs['status']) || !$rs['status']) {
            throw new Exception('门诊记账挂号失败：'.$rs['msg']);
        }

        $datetime = date("Y-m-d H:i:s");
        $param = [
            'his_register_num' => $rs['data']['his_register_num'],
            'updated' => $datetime
        ];

        if (!empty($his_register)) {
            $this->db->where('order_id', $cust_info['order_id']);
            $this->db->where('status = 0 and delete_flag =  0');
            $rt = $this->db->update('intf_order', $param);
        } else {
            $param['created'] = $datetime;
            $param['order_id'] = $cust_info['order_id'];
            $rt = $this->db->insert('intf_order', $param);
        }
        if ($rt) {
            return ['status' => true];
        } else {
            return ['status' => false, 'msg' => '就诊号保存失败'];
        }
    }

    public function reportRecall($param)
    {
        if (!isset($param[0]['report_no']) || !$param[0]['report_no']) {
            return ['error' => 'report_no参数不能为空'];
        }
        $order_ids = [];
        $intfOrderUnion = IntfOrderUnionModel::getInstance();
        try {
            $intfOrderUnion->db->trans_begin();
            foreach ($param as $item) {
                $order_info = $intfOrderUnion->update_report_status($item);
                if (!$order_info) {
                    $intfOrderUnion->db->trans_rollback();
                    return ["error" => "保存失败"];
                } else {
                    $order_ids[] = $order_info["order_id"];
                }
            }
            if ($intfOrderUnion->db->trans_status() === false) {
                $intfOrderUnion->db->trans_rollback();
                return false;
            } else {
                $intfOrderUnion->db->trans_commit();
            }
        } catch (\Exception $e) {
            $intfOrderUnion->db->trans_rollback();
            return ['error' => $e->getTraceAsString()];
        }

        $this->auto_diag_to_save($order_ids);
        IntfAbutmentDetailModel::getInstance()->saveReportLog($param,'reportRecall',[]);
        return 1;
    }


    private function get_unexe_union($order_id)
    {
        $query = $this->db->select('bopu.id as order_pkg_union_id,dau.apply_id,iou.union_barcode,iou.execute_status,iou.id as apply_union_id')
            ->from(BizOrderPackageUnionModel::table_name() . ' bopu')
            ->join(ApplyUnionModel::table_name() . ' dau', 'bopu.union_id=dau.union_id and dau.status=0 and dau.delete_flag = 0')
            ->join(IntfOrderUnionModel::table_name() . ' iou', 'bopu.id = iou.order_pkg_union_id and iou.delete_flag = 0 and iou.status = 0')
            ->where('bopu.order_id', $order_id)
            ->where('bopu.union_execute_flag = 0 and bopu.order_union_option >= 0 and bopu.delete_flag = 0 and bopu.status = 0')
            ->get();
        log_msg( '[get_unexe_union]' . $this->db->last_query());

        return $query->result_array();
    }

    public function perform_status($params)
    {
        $params = json_decode($params,true);
        log_msg( json_encode_unicode($params));

        $param['union_request_no'] = $params['union_request_no'];
        $param['fee_request_code'] = $params['fee_request_no'] ?? '';
        $param['union_barcode'] = isset($params['union_barcode']) ? $params['union_barcode'] : '';
        $param['apply_no'] = isset($params['apply_no']) ? $params['apply_no'] : '';
        $param['union_execute_time'] = isset($params['union_execute_time']) ? $params['union_execute_time'] : '';
        $param['status'] = $params['status'];
        $param['comments'] = $params['comments'];
        $rs = StandApiModel::getInstance()->executeStatus($param);
        IntfStatusDetailModel::getInstance()->saveStatusLog($params,'执行状态',$rs);
        if (!$rs || isset($rs['error'])) {
            return ["error" => "更新失败"];
        }
        return $rs;
    }

    /**
     * 报告回传
     * @param $params
     * @param $scene_code
     * @return array|bool
     */
    public function reportBack($params, $scene_code)
    {
        log_msg(json_encode_unicode($params));
        $arr['exe_status'] = 1;
        $arr['apply_no'] = '';
        $arr['send_content'] = json_encode_unicode(['status' => 1]);
        $arr['scene_code'] = $scene_code;
        $arr['exe_date'] = date('Y-m-d H:i:s');
        $arr['receive_content'] = is_array($params) ? json_encode_unicode($params) : $params;
        $arr['create_date'] = date('Y-m-d H:i:s');

        $rs = \models\intf\AbutmentDetailModel::getInstance()->save($arr);
        if (!$rs) {
            return ["error" => "更新失败"];
        }
        return $rs;
    }

    /**
     * @description
     * 通知费用状态
     *
     * @param array $params
     * @return mixed
     * @since 2021-08-02
     */
    public function check_fee_status($params)
    {
        try {
            $this->load->library('user/util');
            $params =  json_decode($params,true);
            log_msg( '[check_fee_status]' . json_encode_unicode($params));
            $order_info = StandApiModel::getInstance()->checkFeeStatus($params);
            IntfStatusDetailModel::getInstance()->saveStatusLog($params,'费用状态',[]);

            //报存订单队列，定时读取队列发送申请
            $send_request = ParameterModel::getApiConf()['send_request'] ?? false;
            if ($send_request && !empty($order_info['id'])) {
                $this->util->save_order_deal_param(['order_id' => $order_info['id']]);
            }

        } catch (\Exception $e) {
            log_msg(var_export($e->getMessage(), true), 'error');
        }
        return [];
    }

//    /**
//     * @description
//     * 缴费、退费申请
//     *
//     * @return void
//     * @since 2021-08-02
//     */
//    public function send_fee_data($order_id = '')
//    {
//        $apiConfig = ParameterModel::getApiConf();
//
//        $this->load->library('user/util');
//        if (!empty($apiConfig['sync_send_fee_request'])) {
//            $params['order_id'] = $order_id;
//        } else {
//            $param = $this->util->get_send_fee_order();
//            log_msg( '获取缓存：$params==' . $param);
//            $params = json_decode($param, true);
//        }
//
//        if (isset($params['order_id']) && $params['order_id']) {
//            $flag = $this->util->lock_send_fee_task($params['order_id']);
//            if (!$flag) {
//                if (empty($apiConfig['sync_send_fee_request'])) $this->util->save_send_fee_order($params); //报存订单队列
//                log_msg('上一个定时任务执行还在执行中:' . $params['order_id']);
//                return;
//            }
//        } else {
//            $this->util->unlock_send_fee_task($params['order_id']);
//            return;
//        }
//
//        try {
//            $orderInfo = StandApiModel::getInstance()->getOrderInfo($params['order_id']);
//            log_message('debug' ,'[订单信息]' . json_encode($orderInfo, 320),'send_fee_data');
//            if (!$orderInfo) {
//                throw new \Exception('发送缴费申请，未找到订单信息！');
//            }
//
//            $this->sendHisRequest($params['order_id'],$orderInfo);
//
//        } catch (\Exception $e) {
//            log_msg('发送任务异常：' . $e->getMessage(), 'error');
//           // if (empty($apiConfig['sync_send_fee_request'])) $this->util->save_send_fee_order($params); //报存订单队列
//            $this->util->unlock_send_fee_task($params['order_id']);
//            throw new \Exception($e->getMessage());
//        }
//        $flag = $this->util->unlock_send_fee_task($params['order_id']);
//        if (!$flag) {
//            log_msg('锁解发送任务锁失败','error');
//        }
//    }

    /**
     * @description
     * 如果存在两笔收费申请 我们要撤销第一次申请 然后合成一笔收
     *
     * @return void
     * @since 2021-08-02
     */
    public function send_fee_data()
    {
        $this->load->library('user/util');
        $param = $this->util->get_send_fee_order();
        // $param = '{"order_id": 5}';
        log_message('debug', '获取缓存：$params==' . $param, 'send_fee_data');
        $params = json_decode($param, true);

        if (isset($params['order_id']) && $params['order_id']) {
            $flag = $this->util->lock_send_fee_task($params['order_id']);
            if (!$flag) {
                $this->util->save_send_fee_order($params); //报存订单队列
                log_message('error', '上一个定时任务执行还在执行中:' . $params['order_id'], 'send_fee_data_err');
                return;
            }
        } else {
            return;
        }
        $order_id = $params['order_id'];
        $biz_order_model = BizOrderModel::getInstance();

        try {
            $order_status = $biz_order_model->get_info($biz_order_model->table_name(), ['id' => $order_id], 'order_status_code', true);
            if (in_array($order_status['order_status_code'], [0, 1])) {
                log_message('debug', '该订单未登记:' . $params['order_id'], 'send_fee_data');
                throw new \Exception('该订单未登记:' . $params['order_id']);
            }

            $cust_info = $biz_order_model->get_order_customer_info_by_order_id($order_id, false);
            log_message('debug', '[用户详情]' . json_encode($cust_info, 320), 'send_fee_data8888888');
            if (!$cust_info) {
                throw new \Exception('未找到该用户！');
            }

            // 自费
            $this->send_paid_req($order_id, $cust_info, BizOrderPackageUnionModel::UNION_PAY_BY_PERSON);
            $this->send_sub_req($order_id, $cust_info, BizOrderPackageUnionModel::UNION_PAY_BY_PERSON);

            // 团体
            // $this->send_paid_req($order_id, $cust_info, BizOrderPackageUnionModel::UNION_PAY_BY_COMPANY);
            // $this->send_sub_req($order_id, $cust_info, BizOrderPackageUnionModel::UNION_PAY_BY_COMPANY);

        } catch (\Exception $e) {
            log_message('error', '发送任务异常：' . $e->getMessage(), 'send_fee_union_err');
            $this->util->save_send_fee_order($params); //报存订单队列
        }

        $flag = $this->util->unlock_send_fee_task($params['order_id']);
        if (!$flag) {
            log_message('error', '锁解发送任务锁失败');
        }
    }

    /**
     * @description
     * 发送收费申请
     *
     * @param int $order_id
     * @param array $cust_info
     * @return void
     * @since 2021-07-27
     */
    private function send_paid_req($order_id, $cust_info, $pay_code)
    {
        $paid_union_list = BizOrderPackageUnionModel::getInstance()->get_unpay_union_by_order_code($order_id, $pay_code, 0);
        $paid_consume_list = BizOrderPackageConsumeModel::getInstance()->get_unpay_consume_by_order_code($order_id, $pay_code, 0);

        if (!$paid_union_list && !$paid_consume_list) {
            log_message('debug', '没有需要缴费的项目', 'send_fee_data');
            return;
        }

        $start = 0;
        $startCode = $this->db->select('fee_request_code')->from('intf_order_fee_item')->where('order_id', $order_id)->order_by('id', 'DESC')->limit(1)->get()->row_array();
        if ($startCode) {
            $start = strtr(strstr($startCode['fee_request_code'], strval($order_id)), [strval($order_id) => '']);
        }
        $total_fee = 0;

        $update_param = [];
        $update_consume_param = [];

        $feeItemData = [];

        if ($paid_union_list) {
            foreach ($paid_union_list as &$item) {
                $request_code = 'T6' . $this->util->fee_send_trade_batch_num($order_id, $start);
                $item['fee_request_code'] = $request_code;
                $feeItemData[] = $this->getFeeItem($order_id, $item);
                if (!isset($update_param[$item['order_pkg_union_id']])) {
                    $update_param[$item['order_pkg_union_id']] = [
                        'order_pkg_union_id' => $item['order_pkg_union_id'],
                        'fee_request_code' => $request_code,
                        'fee_send_status' => 1,
                    ];
                }
                $total_fee += ($item['his_item_price'] * $item['his_fee_num']);
                unset($item['order_pkg_union_id']);
            }
            unset($item);
        }

        if ($paid_consume_list) {
            foreach ($paid_consume_list as &$item) {
                $request_code = 'T6' . $this->util->fee_send_trade_batch_num($order_id, $start);
                $item['fee_request_code'] = $request_code;

                $feeItemData[] = $this->getFeeItem($order_id, $item);

                if (!isset($update_consume_param[$item['order_pkg_union_id']])) {
                    $update_consume_param[$item['order_pkg_union_id']] = [
                        'id' => $item['order_pkg_union_id'],
                        'fee_request_code' => $request_code,
                        'fee_send_status' => 1,
                    ];
                }
                if ($item['consume_fee_flag']) {
                    $total_fee += ($item['his_item_price'] * $item['his_fee_num']);
                }
                unset($item['order_pkg_union_id']);
            }
            unset($item);
        }

        $total_fee = sprintf('%.2f', bcmul($total_fee, 1, 2));

        $return_data = [
            "apply_type" => "NW",
            'order_code' => $cust_info['order_code'],
            'order_id' => $order_id,
            'fee_request_code' => '',
            'medical_record_no' => $cust_info['medical_record_no'],
            // 'cust_id_card' => $cust_info['cust_id_card'],
            'cust_id' => $cust_info['id'],
            'cmp_org_code' => $cust_info['cmp_org_code'],
            'order_cmp_name' => $cust_info['order_cmp_name'],
            // 'cust_mobile' => $cust_info['cust_mobile'],
            'group_cust_id' => $cust_info['group_cust_id'],
            'pay_code' => $pay_code,
            'cust_name' => $cust_info['cust_name'],
            'create_time' => date('Y-m-d H:i:s'),
            'sys_datetime' => date('Y-m-d H:i:s'),
            'pay_total' => $total_fee,
            'operator_id' => $cust_info['operator_id'],
            'operator_name' => $cust_info['operator_name'],
            'order_register_date' => $cust_info['order_register_date'],
            'send_docker_id' => '1', //发送医生id
            'get_order_docker_id' => '1', //接单医生id
            'employee_id' => 1,
            'items' => json_encode(array_merge($paid_union_list, $paid_consume_list), 320),
        ];
        $this->load->library('user/util');
        $cust_info['mes_num'] = $this->util->get_msg_id();

        $arr = [
            'exe_date' => date('Y-m-d'),
            'scene_code' => 'SEND_HIS_PAY_FEE',
            'send_content' => json_encode($return_data, 320),
            'exe_status' => 0,
            'create_date' => date('Y-m-d'),
            'update_date' => date('Y-m-d'),
        ];
        try {
            $this->chargings($return_data,$return_data["operator_name"],$return_data["operator_id"]);
//            $rs = StandapiClient::call_api_center('receive/send_fee_data', $return_data);
//            $arr['receive_content'] = json_encode($rs, 320);
            if ($update_param) {
                $update_param = array_values($update_param);
                log_message('debug', '[update_param]' . json_encode($update_param), 'send_fee_data');
                $up_rs = $this->db->update_batch(IntfOrderUnionModel::table_name(), $update_param, 'order_pkg_union_id');
            }
            if ($update_consume_param) {
                $update_consume_param = array_values($update_consume_param);
                log_message('debug', '[update_consume_param]' . json_encode($update_consume_param), 'send_fee_data');
                $up_rs = $this->db->update_batch(BizOrderPackageConsumeModel::table_name(), $update_consume_param, 'id');
            }
            if ($feeItemData) {
                log_message('debug', '[intf_order_fee_item]' . json_encode($feeItemData), 'send_fee_data');
                $up_rs = $this->db->insert_batch('intf_order_fee_item', $feeItemData);
            }
            if (!$up_rs) {
                log_message('error', '更新费用发送状态失败：' . json_encode($update_param), 'send_fee_data_err');
            } else {
                //报存订单队列，定时读取队列发送申请
                $send_request = $this->config->item('send_request');
                if ($send_request) {
                    $this->util->save_order_deal_param(['order_id' => $order_id]);
                }
            }
        } catch (\Exception $e) {
            $arr['exe_status'] = 1;
        }
//        \CI_MyApi::excute('intfabutmentdetail/save_report', $arr, 'POST');
    }

    /**
     * DESC: 组装收费信息
     * name: Lemon
     * @param $orderInfo
     * @param $operator
     * @return array
     * @throws \Exception
     */
    public function chargings($orderInfo, $operator, $operatorId)
    {
        $customer = $this->db->where('c.id', $orderInfo['cust_id'])
            ->join('arch_customer_x x', 'x.id = c.id')
            ->get('arch_customer c')
            ->row_array();

        $data['order'] = $orderInfo;

        $data['customer'] = [
            'cust_name' => $customer['cust_name'], //姓名
            'cust_sex' => 2 == $customer['cust_sex_code'] ? 'F' : 'M' , //性别
            'cust_birthday' => $customer['cust_birthday'], //出生日期
            'cust_id_card' => $customer['cust_id_card'], //身份证
            'ccmp_org_code' => $orderInfo['cmp_org_code'], //社会信用代码
            'order_cmp_name' => $orderInfo['order_cmp_name'], //公司名称
            'cust_card_type_code' => $customer['cust_card_type_code'], //身份证类型
            'cust_nation_code' => (int)$customer['cust_nation_code'], //民族
            'cust_mobile' => $customer['cust_mobile'], //家庭电话
            'cust_address' => $customer['cust_address'], //家庭地址
            'profession_code' => 90, //职业代码
            'province' => $customer['province_code'], //省代码
            'city' => $customer['city_code'], //地州代码
            'area' => $customer['district_code'], //区县代码

        ];  //职业代码  省代码 地州代码 县区代码  操作员

        $data['operator'] = $operator;
        $data['operator_id'] = $operatorId;

        if( $orderInfo["apply_type"] == 'NW' ){

            //$union = $this->db->where('order_id', $orderInfo['order_id'])->where('union_disc_flag', 0)->get('biz_order_package_union')->result_array();
            $union = $this->db->where_in('order_union_option', [0,1])->where('order_id', $orderInfo['order_id'])->where('pay_flag', 0)->where('delete_flag', 0)->get('biz_order_package_union')->result_array();

            $unionCodes = array_column($union, 'union_code');


            $chargingUnion = $this->db->select('his_union_code,ipeis_union_code')->where_in('ipeis_union_code', $unionCodes)->get('intf_union_relation')->result_array();
            $chargingUnionCodes = array_column($chargingUnion, 'his_union_code');
            //log_message("debug","【his医生信息】".json_encode($unionCodes,320),__FUNCTION__);
            //log_message("debug","【his医生信息】".json_encode($chargingUnionCodes,320),__FUNCTION__);
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
            $params['param'] = json_encode($data);
            log_message("debug","【his收费信息】".$params['param'],__FUNCTION__);
            StandapiClient::call_api_center('receive/charging', $params);

        }else if( $orderInfo["apply_type"] == 'CA' ){
            $params['param'] = json_encode($data);

            StandapiClient::call_api_center('receive/chargingDel', $params);

        }

    }

    /**
     * @description
     * 发送退费申请
     *
     * @param int $order_id
     * @param array $cust_info
     * @return void
     * @since 2021-07-27
     */
    private function send_sub_req($order_id, $cust_info, $pay_code)
    {
        $sub_union_list = BizOrderPackageUnionModel::getInstance()->get_sub_union_list_by_orderid($order_id, $pay_code);
        $sub_consume_list = BizOrderPackageConsumeModel::getInstance()->get_sub_consume_list_by_orderid($order_id, $pay_code);

        $feeno_list = array_merge($sub_union_list, $sub_consume_list);

        if ($feeno_list) {
//            foreach ($feeno_list as $fee_no => $itemList) {
//                $total_fee = 0;

            $return_data = [
                "apply_type" => "CA",
                'order_code' => $cust_info['order_code'],
                'order_id' => $order_id,
                'fee_request_code' => '',
                'cust_id' => $cust_info['id'],
                'cmp_org_code' => $cust_info['cmp_org_code'],
                'order_cmp_name' => $cust_info['order_cmp_name'],
                'medical_record_no' => $cust_info['medical_record_no'],
                'group_cust_id' => $cust_info['group_cust_id'],
                'pay_code' => $pay_code,
                'cust_name' => $cust_info['cust_name'],
                'create_time' => date('Y-m-d H:i:s'),
                'sys_datetime' => date('Y-m-d H:i:s'),
//                    'pay_total' => $total_fee,
                'operator_id' => $cust_info['operator_id'],
                'operator_name' => $cust_info['operator_name'],
                'order_register_date' => $cust_info['order_register_date'],
                'send_docker_id' => '1', //发送医生id
                'get_order_docker_id' => '1', //接单医生id
                'employee_id' => 1,
//                    "fee_no" => $fee_no,
                'union_codes' => implode(',',array_column($feeno_list,"union_code")),
            ];
//            log_message('error', '发送费用退款数据：' .json_encode_unicode($return_data), 'ggggffffff99999');

//                $arr = [
//                    'exe_date' => date('Y-m-d'),
//                    'scene_code' => 'SEND_HIS_SUB_FEE',
//                    'send_content' => json_encode($return_data, 320),
//                    'exe_status' => 0,
//                    'create_date' => date('Y-m-d'),
//                    'update_date' => date('Y-m-d'),
//                ];
            try {
                log_message('error', '发送费用退款数据：' .json_encode_unicode($return_data), 'send_fee_data_err88888888');
                $this->chargings($return_data,$return_data["operator_name"],$return_data["operator_id"]);
//                    $rs = StandapiClient::call_api_center('receive/send_fee_data', $return_data);
//                    $arr['receive_content'] = json_encode($rs, 320);
            } catch (\Exception $e) {
                $arr['exe_status'] = 1;
            }
//                \CI_MyApi::excute('intfabutmentdetail/save_report', $arr, 'POST');
//            }
        }
    }

    /**
     * @description
     * 发送his申请
     *
     * @param int $order_id
     * @param array $orderInfo
     * @return void
     * @since 2021-07-27
     */
    private function sendHisRequest($order_id, $orderInfo)
    {
        $apiConfig = ParameterModel::getApiConf();
        $send_detail_flag = !empty($apiConfig['send_detail_flag']);//是否发送收费明细
        $group_send_flag = !empty($apiConfig['group_send_flag']);//是否团检项目
        $consume_send_flag = !empty($apiConfig['consume_send_flag']);//耗材是否发送申请
        $stop_cancel_send_flag = !empty($apiConfig['stop_cancel_send_flag']);//是有撤回退费申请接口
        $list = $unionList = StandApiModel::getInstance()->getHisUnSendData($order_id,$send_detail_flag,$group_send_flag);

        if ($consume_send_flag) {
            $consumeList = StandApiModel::getInstance()->getUnSendConsumeData($order_id,$send_detail_flag,$group_send_flag);
            $list = array_merge($unionList,$consumeList);
        }

        $newList = []; //新订单
        $stopList = []; //未缴费，停用申请
        $cancelList = []; //已缴费，退费申请
        $stopCancelList = []; //已发送退费申请，撤回退费申请

        foreach ($list as $item) {
            if ($stop_cancel_send_flag && $item['fee_send_status'] == 0 && $item['pay_flag'] == OrderPackageUnionModel::PAY_FLAG_YES) {
                $stopCancelList[] = $item;
            } else if ($item['fee_send_status'] == 0) {
                $newList[] = $item;
            } else if ($item['fee_send_status'] == 1 && $item['pay_flag'] == OrderPackageUnionModel::PAY_FLAG_YES) {
                $cancelList[] = $item;
            } else if ($item['fee_send_status'] == 1 && $item['pay_flag'] == OrderPackageUnionModel::PAY_FLAG_NO) {
                $stopList[] = $item;
            } else {
                throw new Exception('发送HIS申请失败，未知发送类型');
            }
        }

        $applyDept = $this->apply_dept;
        $orderInfo['apply_dept_code'] = $applyDept['apply_dept_code'];//申请科室
        $orderInfo['apply_dept_name'] = $applyDept['apply_dept_name'];//申请科室

        if ($newList) {
            $sendData = ['type' => 'NW','list' => json_encode_unicode($newList),'order_info' => json_encode_unicode($orderInfo)];
            $returnData = self::call_api(StandapiEnum::HIS_FEE_APPLY, $sendData);
            if ($returnData['status']) {
                $rs = StandApiModel::getInstance()->updateFeeSendStatus($newList,StandApiModel::FEESEND_NW);
                if (!$rs) {
                    throw new Exception('更新缴费申请状态失败！');
                }
            } else {
                IntfSendDetailModel::getInstance()->saveSendLog($returnData,'HIS申请',$sendData);
                throw new Exception('医嘱申请失败！');
            }
            IntfSendDetailModel::getInstance()->saveSendLog($returnData,'HIS申请',$sendData);
        }

        if ($stopList) {
            $sendData = ['type' => 'ST','list' => json_encode_unicode($stopList),'order_info' => json_encode_unicode($orderInfo)];
            $returnData = self::call_api(StandapiEnum::HIS_FEE_APPLY, $sendData);
            if ($returnData['status']) {
                $rs = StandApiModel::getInstance()->updateFeeSendStatus($stopList,StandApiModel::FEESEND_ST);
                if (!$rs) {
                    throw new Exception('更新缴费申请状态失败！');
                }
            } else {
                IntfSendDetailModel::getInstance()->saveSendLog($returnData,'HIS停用申请',$sendData);
                throw new Exception(implode(',',array_unique(array_column($stopList,'union_name'))).'医嘱停用申请失败！');
            }
            IntfSendDetailModel::getInstance()->saveSendLog($returnData,'HIS停用申请',$sendData);
        }

        foreach ($cancelList as $cancelItem) {
            $sendData = ['type' => 'CA','list' => json_encode_unicode([$cancelItem]),'order_info' =>  json_encode_unicode($orderInfo)];
            $returnData = self::call_api(StandapiEnum::HIS_FEE_APPLY, $sendData);
            if ($returnData['status']) {
                $rs = StandApiModel::getInstance()->updateFeeSendStatus([$cancelItem],StandApiModel::FEESEND_CA);
                if (!$rs) {
                    throw new Exception('更新缴费申请状态失败！');
                }
            } else {
                IntfSendDetailModel::getInstance()->saveSendLog($returnData,'HIS退费申请',$sendData);
                throw new Exception($cancelItem['union_name'].'医嘱退费申请失败！');
            }
            IntfSendDetailModel::getInstance()->saveSendLog($returnData,'HIS退费申请',$sendData);
        }

        foreach ($stopCancelList as $stopCancelItem) {
            $sendData = ['type' => 'STC','list' => json_encode_unicode([$stopCancelItem]),'order_info' =>  json_encode_unicode($orderInfo)];
            $returnData = self::call_api(StandapiEnum::HIS_FEE_APPLY, $sendData);

            if ($returnData['status']) {
                $rs = StandApiModel::getInstance()->updateFeeSendStatus([$stopCancelItem],StandApiModel::FEESEND_STC);
                if (!$rs) {
                    throw new Exception('更新缴费申请状态失败！');
                }
            } else {
                IntfSendDetailModel::getInstance()->saveSendLog($returnData,'撤回HIS退费申请',$sendData);
                throw new Exception($stopCancelItem['union_name'].'撤回医嘱退费申请失败！');
            }
            IntfSendDetailModel::getInstance()->saveSendLog($returnData,'撤回HIS退费申请',$sendData);
        }
    }

    public function order_register($param)
    {
        $group_cust_id = empty($param['group_cust_id']) ? '' : $param['group_cust_id'];
        $order_id = $param['order_id'];
        $pay_way = empty($param['pay_way']) ? 0 : $param['pay_way'];
        $param['order_register_way_code'] = empty($param['order_register_way_code']) ? 'R020' : $param['order_register_way_code']; //登记方式--未传入，默认人脸识别

//        log_msg( 'execute_self_machine_save录入参数：' . json_encode($param));
//        $rs = \CI_MyApi::excute('orderinfo/execute_self_machine_save', $param, 'POST');
//        log_msg( 'execute_self_machine_save' . json_encode($rs));
//
        $param['user_id'] = 0;
        $param['user_name'] = '';
        $param['is_checkin_queue'] = [config_item("default_area_code")];
        $param['area_code'] = config_item("default_area_code");
        $areaList = ClassifyItemModel::getInstance()->get_area_type_list();
        $areaList = array_column($areaList,null,'item_code');
        $param['exam_area_id'] = $areaList[$param['area_code']]['id'];


        if($order_id){
            $res = OrderBatchRegisterService::getInstance()->groupRegisterWithOrderId($param);
        } elseif ($group_cust_id) {
            $res =  OrderBatchRegisterService::getInstance()->groupRegisterWithOutOrderId($param);
            if(isset($res['order_id'])){
                $order_id = $res['order_id'];
            }
        }

        if(isset($res['error'])) return ['status'=>false,'msg'=>$res['error'],'code'=>-1];

        if (isset($res['group_cust_id']) && $res['group_cust_id'] != '') {
            $group_cust_id = $res['group_cust_id'];
        }
        //建档申请
        $create_patient_info['user_emp_num'] = (isset($param['user_name']) ? $param['user_name'] : '-');
        $create_patient_info['user_id'] = (isset($param['user_id']) ? $param['user_id'] : 0);
        $create_patient_info['user_name'] = (isset($param['user_name']) ? $param['user_name'] : '-');
        $create_patient_info['order_id'] = $order_id;
        $create_patient_info['cust_id'] = $res['cust_id'];
        $medical_num = "";
        try {
            $medical_num = $this->patientCreateArchive($create_patient_info);
        }catch (Exception $e) {

        }


        //region 生成条码号
        $tmp_param['order_id'] = $res['order_id'];
        $tpr = \CI_MyApi::excute('order/create_union_number', $tmp_param);
        //endregion

        //region 其它功能处理
        $deal_param = array(
            'order_id' => $res['order_id'],
            'cust_id' => $res['cust_id'],
            'order_appt_date' => $res['order_appt_date'],
            'order_status_code' =>$res['order_status_code'],
            'group_cust_id' => $group_cust_id,
            'pay_way' => $pay_way,
            'order_code' => $res['order_code'],
            'register_flag' => 'Y',
            'user_id' => '0',
            'user_name' => '0',
            'union_add_list' => [],
            'pkg_id' => $res['pkg_id'],
            'medicla_num' => $medical_num
        );
        $barcodeConf = \models\priv\ParameterModel::getByParamCodeJson(\extend\enums\ParameterEnum::BARCODE_CONF);
        //检验科是否本地生成条码
        $barcode_make = $barcodeConf['barcode_make'];
        if (!$barcode_make && isset($rs['data']['order_id'])) {
            $this->send_lis_request($rs['data']['order_id']);
        }

        $this->load->library('user/util');
        log_msg( 'order_register：压入缓存前：$params==' . json_encode($deal_param));
        \extend\queue\QueueOrder::getInstance()->pushWaitOrder($deal_param['order_id']);
        $r = $this->util->save_order_deal_param($deal_param);
        if (!$r) {
            log_msg('deal_param压入缓存失败','error');
        }
        //endregion
        unset($res['cust_info']);
        return array('status' => true, 'code' => 0, 'msg' => '成功', 'data' => $res);

    }
}
