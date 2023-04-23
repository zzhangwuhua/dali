<?php


namespace models\arch;


use helpers\LogHelper;
use Exception;
use models\BaseModel;
use models\biz\GroupCustomerModel;
use models\classify\ClassifyItemModel;
use models\group\GroupModel;
use models\intf\CompanyModel as IntfCompanyModel;
use models\intf\IntfCustomerModel;

/**
 * Class CustomerModel
 * @package models\arch
 * @property int $id 客户id
 * @property int $status 有效标识，0：有效，1：无效
 * @property int $delete_flag 软删除标志，0：未删除，1：删除
 * @property string $created 创建时间
 * @property int $created_by 创建者
 * @property string $updated 最后更新时间
 * @property int $updated_by 最后更新者
 * @property string $cust_name 客户姓名
 * @property int $cust_sex_id
 * @property int $cust_sex_code
 * @property int $cust_card_type_id 证件类型id
 * @property int $cust_card_type_code 证件类型code
 */
class CustomerModel extends BaseModel
{
    public $table_name = 'arch_customer';


    public function cust_num($data)
    {
        if($data == [] || $data == null)
        {
            return $this->getCustNum();
        }
        elseif (isset($data['cust_num_type']) && isset($data['group_prefix']))
        {
            return $this->getCustNum($data['cust_num_type'],$data['group_prefix']);
        }
        elseif (!isset($data['cust_num_type']) && isset($data['group_prefix']))
        {
            return $this->getCustNum(1,$data['group_prefix']);
        }
        elseif (isset($data['cust_num_type']) && !isset($data['group_prefix']))
        {
            return $this->getCustNum($data['cust_num_type']);
        }

    }

    /**
     * 生成档案号
     * @param int $cust_num_type
     * @param null $group_prefix
     * @return string|null
     */
    public function getCustNum($cust_num_type = 0, $group_prefix = null)
    {
        //region 生成档案号
        log_msg('Message: 生成档案号开始 ');
        $indiv_default_num = '00000001';//个人默认档案号
        $indiv_default_prefix = '0'; //个人默认前缀
        $group_default_num = $group_prefix ? $group_prefix . '0000001' : '80000001';//团检默认档案号
        $group_default_prefix = $group_prefix ?: '8';//团检默认前缀
        $pad_length = 8; //档案号长度
        if ($cust_num_type != 1 && $group_prefix) {
            return null;
        }


        $this->load->library('user/util');
        $this->db->select_max('cust_num', 'cust_num');
        $this->db->like('cust_num', $cust_num_type == 0 ? $indiv_default_prefix : $group_default_prefix, 'after');
        log_msg('Message: $cust_num_type: ' . $cust_num_type . ',$cust_num_type:' . $cust_num_type);
        if ($cust_num_type == 0 || $cust_num_type == 1) {
            try {
                $result = $this->db->get($this->table_name)->row_array();
                $cust_num = $result['cust_num'];
                log_msg('Message: $cust_num 最后值: ' . $cust_num);
                if ($cust_num && $cust_num_type == 0) {
                    $cust_num = $this->util->get_cust_num_task(intval($cust_num) + 1);
                }
                log_msg('Message: $cust_num 下一个值: ' . $cust_num);
                $cust_num = $cust_num == null ? ($cust_num_type == 0 ? $indiv_default_num : $group_default_num) :
                    ($cust_num_type == 0 ? str_pad(intval($cust_num) + 1, $pad_length, '0', STR_PAD_LEFT) :
                        $group_prefix . str_pad(intval($group_prefix ? substr($cust_num, strlen($group_prefix)) : $cust_num) + 1, $pad_length - strlen($group_prefix), '0', STR_PAD_LEFT)
                    );
                return $cust_num;
            } catch (\Exception $e) {
                log_msg('Message: ' . $e->getMessage());
                return null;
            }
        }
        log_msg('生成档案号失败', 'error');
        return null;
        //endregion
    }

    /**
     * 获取对应的证件类型
     * @param string $key
     * @param string $classify_code
     * @return array|array[]
     */
    public function getAllCardTypes($key = '', $classify_code = '0068')
    {
        $this->db->select("id, item_code, item_name", false);
        $this->db->where('classify_code', $classify_code);
        $this->db->where('status', 0);
        $this->db->where('delete_flag', 0);
        $query = $this->db->get('com_classify_item');
        $res = $query->result_array();

        $aReturn = [];
        if ($key) {
            foreach ($res as $v) {
                $aReturn[$v[$key]] = $v;
            }
        }

        return $key ? $aReturn : $res;
    }


    /**
     * 通过身份证号获取生日
     * @param $sBodyCardNo
     * @return string|null
     */
    public function getBirthdayByBodyCard($sBodyCardNo)
    {
        $vCity = array(
            '11', '12', '13', '14', '15', '21', '22',
            '23', '31', '32', '33', '34', '35', '36',
            '37', '41', '42', '43', '44', '45', '46',
            '50', '51', '52', '53', '54', '61', '62',
            '63', '64', '65', '71', '81', '82', '91'
        );

        if (!preg_match('/^([\d]{17}[xX\d]|[\d]{15})$/', $sBodyCardNo)) return null;

        if (!in_array(substr($sBodyCardNo, 0, 2), $vCity)) return null;

        $sBodyCardNo = preg_replace('/[xX]$/i', 'a', $sBodyCardNo);
        $vLength = strlen($sBodyCardNo);

        if ($vLength == 18) {
            $vBirthday = substr($sBodyCardNo, 6, 4) . '-' . substr($sBodyCardNo, 10, 2) . '-' . substr($sBodyCardNo, 12, 2);
        } else {
            $vBirthday = '19' . substr($sBodyCardNo, 6, 2) . '-' . substr($sBodyCardNo, 8, 2) . '-' . substr($sBodyCardNo, 10, 2);
        }

        if (date('Y-m-d', strtotime($vBirthday)) != $vBirthday) return null;
        if ($vLength == 18) {
            $vSum = 0;

            for ($i = 17; $i >= 0; $i--) {
                $vSubStr = substr($sBodyCardNo, 17 - $i, 1);
                $vSum += (pow(2, $i) % 11) * (($vSubStr == 'a') ? 10 : intval($vSubStr, 11));
            }

            if ($vSum % 11 != 1) return null;
        }

        return $vBirthday;
    }


    /**
     * 根据获取指定条件的customer
     * @param array $data
     * @param string $fields
     * @return array|array[]
     */
    public function getCustomerByOptions(array $data, $fields = '*')
    {
        $query = $this->db->select("u.cust_num, u.cust_sex_id, u.cust_sex_code, u.cust_id_card,, u.cust_name, c.cmp_name,
        g.cust_age,u.cust_mobile,u.id
        ");

        if (isset($data['and']) && !empty($data['and'])) {
            foreach ($data['and'] as $k => $v) {
                $this->db->where($k, $v);
            }
        }

        $result = $query
            ->join('arch_customer_company ua', 'ua.cust_id = u.id')
            ->join('arch_company c', 'c.id = ua.cmp_id')
            ->join('biz_group_customer g', 'g.cust_id = u.id')
            ->where('u.status', 0)
            ->where('u.delete_flag', 0)
            ->get($this->table_name. ' u')
            ->result_array();
        LogHelper::log($this->db->last_query());

        return $result;

    }

    public static $aMarriageStatuses = []; //静态变量存储

    /**
     * 获取婚姻id以及code
     * @param array $aAllMarriageStatuses
     * @param $sMarriageStatus
     * @return array
     */
    public function getMarriageIdAndCode(array $aAllMarriageStatuses, $sMarriageStatus)
    {
        if (isset(self::$aMarriageStatuses[$sMarriageStatus])) {
            $allMarriageStatus = self::$aMarriageStatuses[$sMarriageStatus];

            return  [$allMarriageStatus['id'], $allMarriageStatus['item_code']];
        }
        foreach ($aAllMarriageStatuses as $allMarriageStatus) {
            if ($allMarriageStatus['item_name'] == $sMarriageStatus) {
                if (!isset(self::$aMarriageStatuses[$sMarriageStatus])) {
                    self::$aMarriageStatuses[$sMarriageStatus] = $allMarriageStatus;
                }

                return  [$allMarriageStatus['id'], $allMarriageStatus['item_code']];
            }
        }

        return [$allMarriageStatus['id'], $allMarriageStatus['item_code']];
    }

    public function getCustomerIdByConditions(array $condition)
    {
        $res = $this->db->select('id')
            ->where('status = 0 and delete_flag = 0')
            ->where($condition)
            ->get($this->table_name)
            ->row();
        if($res) {
            return $res->id;
        } else {
            return null;
        }
    }


    public function get_patient_create_data($cust_id) {
        return $this->db->select("a.cust_num,ax.cust_marriage_code,a.cust_sex_code,a.cust_name,h.item_name as cust_sex_name,i.item_name as cust_marriage_name,a.cust_id_card,a.cust_mobile,a.cust_birthday,". $this->getAgeFromCust('a.cust_birthday')." as cust_age
        ,b.medical_record_no,c.id as group_cust_id,f.id as cmp_id,g.origin_cmp_id,g.sync_status", false)
            ->from($this->table_name . ' a')
            ->join(CustomerXModel::table_name() . ' ax', 'a.id=ax.id', 'left')
            ->join(IntfCustomerModel::table_name() . ' b', 'a.id = b.cust_id and b.`status`=0 and b.delete_flag=0', 'left')
            ->join(GroupCustomerModel::table_name() . ' c', 'a.id=c.cust_id and c.`status`=0 and c.delete_flag=0', 'left')
            ->join(GroupModel::table_name() . ' d', 'c.group_id=d.id and d.`status`=0 and d.delete_flag=0', 'left')
            ->join(GroupModel::table_name() . ' e', 'd.par_group_id=e.id and e.`status`=0 and e.delete_flag=0', 'left')
            ->join(CompanyModel::table_name() . ' f', 'e.cmp_id=f.id and f.`status`=0 and f.delete_flag=0', 'left')
            ->join(IntfCompanyModel::table_name() . ' g', 'f.id=g.cmp_id and g.`status`=0 and g.delete_flag=0', 'left')
            ->join(ClassifyItemModel::table_name() . ' h', 'a.cust_sex_id=h.id', 'left')
            ->join(ClassifyItemModel::table_name() . ' i', 'ax.cust_marriage_id=i.id', 'left')
            ->where('a.`status`=0 and a.delete_flag=0 ')
            ->where('a.id', $cust_id)
            ->get()
            ->row_array();

    }

    /**
     * @param $cust_id
     * @param $medical_record_no
     * @param $admin_id
     * @param string $clinic_id
     * @param int $data_type
     * @throws Exception
     */
    public function save_intf_customer($cust_id,$medical_record_no,$admin_id, $clinic_id = '',$data_type=1){

        $rs = $this->get_info(IntfCustomerModel::table_name(),['cust_id'=>$cust_id],"id",true);
        if($rs) {
            $param['id'] = $rs['id'];
        }

        $param['cust_id'] = $cust_id;
        $param['medical_record_no'] = $medical_record_no;
        $param['clinic_id'] = $clinic_id;
        $param['data_type'] = $data_type;

        log_msg('save_intf_customer'.json_encode($rs).'保存项目:'.json_encode($param));

        $this->db->trans_start();

        $rs1 = $this->save(['cust_id'=>$cust_id,'medical_record_no'=>$medical_record_no,'data_type'=>$data_type],'intf_customer_x',$admin_id);
        $rs2 = $this->save($param, 'intf_customer',$admin_id);
        if(!$rs1 || !$rs2) {
            $this->db->trans_rollback();
            log_msg('保存档案和流水失败rs1:'.$rs1."-rs2".$rs2);
            throw new Exception('保存修改档案失败');
        }
        $this->db->trans_complete();
        log_msg('保存档案和流水成功');
        return true;

    }

    public function getCustomerInfo($id)
    {
        return $this->db->select()
            ->join('arch_customer_x b', 'a.id = b.id')
            ->where('a.id', $id)
            ->get($this->table_name. ' a')
            ->row_array();
    }

    /**
     * 通过手机号获取档案
     * @param $mobile
     * @return array
     */
    public function getCustomerByMobile($mobile){
       return $this->db->select("*")->from($this->table_name)
            ->where("delete_flag",0)
            ->where("status",0)
            ->where("cust_mobile",$mobile)
            ->get()->result_array();
    }

    /**
     * 通过证件号和姓名
     * @param $param
     * @return array
     */
    public function getCustomerByCardName($param){
        $cust_id_card = $param['cust_id_card'];
        $cust_name = $param['cust_name'];
        return $this->db->select("*")->from($this->table_name)
            ->where("delete_flag",0)
            ->where("status",0)
            ->where("cust_id_card",$cust_id_card)
            ->where("cust_name",$cust_name)
            ->get()->result_array();

    }

    /**
     * 通过批量id获取证件类型
     * @param $ids
     * @return array|false
     */
    public function getCardTypeByIds($ids){
        $ids = array_unique($ids);
        if(!empty($ids)){
            $query = $this->db->where_in('id',$ids)
                ->where("delete_flag",0)
                ->where("status",0)
                ->select('id,item_name')
                ->get('com_classify_item')->result_array();
            $array = array_column($query,'item_name','id');
            return $array;
        }
        return [];
    }

    public function getCustomerProfile(array $ids=[0], string $fields = 'id,cust_name')
    {

        return $this->db->select($fields,false)
            ->where_in('id', $ids)
            ->get($this->table_name)
            ->result_array();
    }

    /**
     * 批量获取用户信息
     *
     * @param array $ids
     * @param string $field
     * @return array
     */
    public function getRowsByIds(array $ids=[0], string $field = '*')
    {
        $query = $this->db->select($field, false)->where_in('id', $ids)->get($this->table_name);
        return $query->result_array();
    }

    public function getCustomerByCardIdAndName($param,$otherCondition = '')
    {
        $query  =  $this->db->select('ac.id cust_id,ac.cust_num,ac.cust_name,ac.cust_id_card,ac.cust_mobile,
        ac.cust_card_type_code,ac.cust_birthday,acx.cust_address,acx.id acx_id,ac.cust_sex_code,ac.cust_sex_id')
            ->from('arch_customer ac')
            ->join('arch_customer_x acx','acx.id = ac.id and acx.delete_flag = '.self::NOT_DELETED,'left')
            ->where('ac.delete_flag',self::STATUS_ENABLE)
            ->where('ac.cust_id_card',$param['cust_id_card'])
            ->where('ac.cust_card_type_code',$param['cust_card_type_code']);

        if($otherCondition){
            $query->where($otherCondition);
        }
        $rs = $query->get()->result_array();
        return $rs;
    }

    public function get_customer_by_order($data)
    {
        $order_id = $data['order_id'] = intval($data['order_id']);
        $group_cust_id = $data['group_cust_id'] = intval($data['group_cust_id']);
        $show_flag = $data['show_flag'];
        if($show_flag == '0')
        {
            $this->db->select('a.id cust_id,'.$this->getYearFromCust('a.cust_birthday'). ' AS cust_age,a.cust_num,a.cust_name,a.cust_sex_id,a.cust_sex_code,a.cust_id_card,a.cust_card_type_id,a.cust_card_type_code,a.cust_birthday,a.cust_mobile,b.cust_marriage_id,b.cust_photo_addr, cust_marriage_code,b.permanent_addr,')
                ->from('arch_customer a')
                ->join('arch_customer_x b', 'a.id = b.id', 'left');

            if($order_id)
            {
                $this->db->where('a.id in (select d.cust_id from biz_order d where d.id='.$order_id.')','',false);
            }
            else if($group_cust_id)
            {
                $this->db->where('a.id in (select d.cust_id from biz_group_customer d where d.id='.$group_cust_id.')','',false);
            }
            $result = $this->db->get();
            //$this->add_log($this->db->last_query());
            return $result->result_array();
        }
        else if($show_flag == '1')
        {
            return $this->get_customer($data);
        }

    }

    private function get_customer($data)
    {
        $this->db->select('a.id as cust_id,a.*,d.par_item_id as province_id,d.par_item_code as province_code,d.par_item_name as province_name,
d.item_id as city_id,d.item_code as city_code,d.item_name as city_name,'.$this->getYearFromCust('a.cust_birthday'). ' AS cust_age,
                            e.`cmp_id`,e.`cmp_dept_id`,e.`cust_onduty_id`,e.`cust_onduty_code`,e.`cust_job`,e.`cust_job_grade_code`,e.`cust_job_grade_id`,
                            a.cust_card_type_id,
                            a.cust_card_type_code,
                            b.cust_marriage_id,
                            b.cust_nation_id,
                            b.cust_education_id,
                            b.cust_marriage_code,
                            b.cust_nation_code,
                            b.cust_education_code,
                            b.cust_telephone,
                            b.cust_zipcode,
                            b.cust_qq,
                            b.cust_wechat,
                            b.cust_email,
                            b.cust_address,
                            b.cust_health_num,
                            b.cust_health_level_id,
                            b.cust_health_level_code,
                            b.cust_med_ins_fund,
                            b.cust_fund_form_id,
                            b.cust_fund_form_code,
                            b.cust_desc,
                            b.cust_online_flag,
                            b.cust_email_flag,
                            b.cust_message_flag,
                            b.cust_photo_addr,f.medical_record_no,b.permanent_addr');
        $this->db->from('arch_customer a');
        $this->db->join('arch_customer_x b', 'a.id = b.id and b.delete_flag = \'0\'', 'left');
        $this->db->join("(select a.id par_item_id,a.item_code as par_item_code,a.item_name as par_item_name,
ifnull(b.id,a.id) item_id,ifnull(b.item_code,a.item_code) as item_code,ifnull(b.item_name,a.item_name) as item_name
 from com_classify_item a left outer join com_classify_item b
on ( b.classify_code = '0041'
    and a.classify_code = b.par_classify_code
    and a.item_code = b.par_item_code)
where a.classify_code = '0003') d ",'a.cust_native_place_id = d.item_id','left');
        $this->db->join("arch_customer_company e"," a.id = e.cust_id and current_date between e.effective_date and e.expiry_date and e.status = 0 and e.delete_flag = 0","left",FALSE);
        $this->db->join("intf_customer f"," a.id = f.cust_id and f.status = 0 and f.delete_flag = 0","left",FALSE);
        $this->db->where("a.status = ",0);
        $this->db->where("a.delete_flag = ",0);
        if(isset($data['id']) && !empty($data['id']))
        {
            $this->db->where("a.id = ",$data['id']);
        }

        if(isset($data['cust_id_card']) && !empty($data['cust_id_card']))
        {
            $this->db->where("a.cust_id_card = ", trim($data['cust_id_card']));
        }

        if(isset($data['cust_card_type_code']) && !empty($data['cust_card_type_code']))
        {
            $this->db->where("a.cust_card_type_code = ",$data['cust_card_type_code']);
        }

        if(isset($data['cust_name']) && !empty($data['cust_name']))
        {
            $this->db->where("a.cust_name = ", trim($data['cust_name']));
        }

        if(isset($data['cust_mobile']) && !empty($data['cust_mobile']))
        {
            $this->db->where("a.cust_mobile = ", trim($data['cust_mobile']));
        }

        if(isset($data['cust_sex_id']) && !empty($data['cust_sex_id']))
        {
            $this->db->where("a.cust_sex_id = ",$data['cust_sex_id']);
        }

        if(isset($data['order_id']) && !empty($data['order_id']))
        {
            $this->db->where('a.id in (select d.cust_id from biz_order d where d.id='.$data['order_id'].')','',false);
        }
        else if(isset($data['group_cust_id']) && !empty($data['group_cust_id']))
        {
            $this->db->where('a.id in (select d.cust_id from biz_group_customer d where d.id='.$data['group_cust_id'].')','',false);
        }

        $result = $this->db->get();
        log_msg($this->db->last_query());
        return $result->result_array();
    }


    /**
     * 通过cust_id 查找省代码&区代码
     * @param int $cust_id
     * @return array
     */
    public function getProvinceCityByCustId(int $cust_id):array
    {
        $this->db->select("b.item_code as 'city_code',c.item_code as 'province_code'",false);
        $this->db->join("com_classify_item as b","a.cust_native_place_id = b.id and classify_code = '0041'","left");
        $this->db->join("com_classify_item as c","c.item_code = b.par_item_code","left");
        $this->db->where("a.id",$cust_id);
        $this->db->where("a.delete_flag = 0 and a.`status` = 0");
        $this->db->where("b.delete_flag = 0 and b.`status` = 0");
        $this->db->where("c.delete_flag = 0 and c.`status` = 0");
        $res = $this->db->get($this->table_name." as a")->row_array();
        return $res??[];
    }

}
