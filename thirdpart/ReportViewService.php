<?php

namespace services\thirdpart;

use services\BaseService;
use models\standapi\StandApiModel;

/**
 *
 * 视图或表
 * Class ReportViewService.php
 * @package services\thirdpart
 *
 */
class ReportViewService extends BaseService
{

    protected $standApiModel;
    protected $view_report_api = [];
    protected $view_db = null;

    function __construct()
    {
        parent::__construct();
        $this->standApiModel = StandApiModel::getInstance();
    }


    function response($status,$msg='',$data=array()){
        return  array('status'=>$status,'msg'=>$msg,'data'=>$data);
    }

    function ajax_suc($data=array(),$msg='成功'){
        return $this->response(true,$msg,$data);
    }
    function ajax_fail($msg='失败',$data=array(),$code='-1'){
        return $this->response(false,$msg,$data,$code);
    }

    /**
     * 同步视图中间表报告
     * @param $api_key
     * @return array|bool
     */
    public function sys_view_report($api_key)
    {
        $rs = $this->set_view_report_api_config($api_key);
        if(isset($rs['error'])){
            return $rs;
        }
        $report_data = $this->get_view_report_data();
        if(!$report_data){
            log_msg($api_key.'接口无需要同步的报告数据');
            return true;
        }
        $group_type = $this->view_report_api['group_type'];//分组方式，0：以组合分组，1：以条码分组，2：以项目分组

        switch ($group_type) {
            case 1:
                $group_key = 'union_barcode';
                break;
            case 2:
                $group_key = 'item_code';
                break;
            case 3:
                $group_key = 'union_request_no';
                break;
            default:
                $group_key = 'union_code';
                break;
        }
        if($group_key == 'item_code'){//以项目分组
            foreach ($report_data as $report_data_item){
                $rs = $this->save_data([$report_data_item]);
                if($rs){
                    $this->update_status($report_data_item['item_code']);
                }
            }
            return true;
        }else{ //以组合分组，以条码分组
            $report_group_data = [];
            foreach ($report_data as $report_data_item){
                $report_group_data[$report_data_item[$group_key]][] = $report_data_item;
            }
        }

        foreach ($report_group_data as $report_group_data_item){
            $rs = $this->save_data($report_group_data_item);
            if($rs){
                $this->update_status($report_group_data_item[0][$group_key]);
            }
        }

    }


    /**
     * 设置配置
     * @param $api_key
     * @return array|bool
     */
    protected function set_view_report_api_config($api_key)
    {
        $view_report_api_config = config_item('view_report_api_config');
        if(empty($view_report_api_config[$api_key])){
            return ['error' => $api_key.'接口未配置'];
        }
        $this->view_report_api = $view_report_api_config[$api_key];
        return true;
    }

    /**
     * 获取数据
     * @return mixed
     */
    protected function get_view_report_data()
    {
        $config_db = $this->view_report_api['config_db'];
        $api_sql = $this->view_report_api['api_sql'];

        if($config_db == 'default'){ //默认BS数据库
            $this->view_db = $this->load->database($config_db, TRUE);
        }else{
            $this->view_db = $this->db;
        }
        return $this->view_db->query($api_sql)->result_array();
    }

    protected function save_data($data)
    {
        $api_type = config_item('api_type');
        if($api_type){
            return $this->save_lis_data($data);
        }else{
            return $this->save_check_result($data);
        }
    }

    protected function save_lis_data($data)
    {
        log_msg(json_encode_unicode($data));
        $param['id'] = '';
        $param['lis_union'] = json_encode_unicode($data);
        $rs =  \CI_MyApi::excute('Reportresult/save_lis_result', $param, 'POST');
        if($rs['status']){
            return true;
        }else{
            log_msg($rs['msg'].',消息：'.json_encode_unicode($data));
            return false;
        }
    }

    protected function save_check_result($data)
    {
        $param['id'] = '';
        $param['check_union'] = json_encode_unicode($data);
        $rs = \CI_MyApi::excute('Reportresult/save_check_result',$param,'POST');
        if($rs['status']){
            return true;
        }else{
            log_msg($rs['msg'].',消息：'.json_encode_unicode($data));
            return false;
        }
    }

    protected function update_status($value)
    {
        $update_status_sql = $this->view_report_api['update_status_sql'];
        return $this->view_db->query($update_status_sql,$value);
    }

}
