<?php

namespace services\thirdpart;

use models\standapi\StandLisModel;
use services\BaseService;

class LisService extends BaseService
{
    const lock_key = 'SEND_SAMPLE_APPLY_KEY';
    const send_sample_key = 'LIS_SEND_SAMPLE_KEY';
   # const KM_TOKEN = '';
    public function __construct() {

      //  ini_set('mssql.textlimit',4294967296);
      //  ini_set('mssql.textsize',4294967296);
        parent::__construct();
    }

    /**
     * 发送标本采血状态到标本运输系统
     */
    public function send_sample_apply_data()
    {
        $this->load->library('user/util');
        $n = 5;
        $param_arr = [];
        for ($i = 1;$i < $n;$i++){
            if($param_tmp = $this->util->get_redis_queue(self::send_sample_key)){//单个登记队列
                $param_tmp = json_decode($param_tmp, true);
                $param_tmp['queue_type'] = 0;
                $param_arr[$param_tmp['order_id']] = $param_tmp;
            }
        }
        log_message('debug', '获取缓存：$params==' . json_encode_unicode($param_arr), 'send_sample_apply_data');
        try {
            foreach ($param_arr as $params){
                $this->send_common($params);
            }
        } catch (\Exception $e) {
            log_message('error', '发送任务异常：' . $e->getMessage(), 'send_sample_apply_data_err');
        }
    }

    public function send_common($params)
    {
        try{
            if (isset($params['order_id']) && $params['order_id']) {
                $flag = $this->util->lock_task($params['order_id'],self::lock_key);
                if (!$flag) {
                    $this->util->save_redis_queue($params,self::send_sample_key);
                    log_message('error', '上一个定时任务执行还在执行中:' . $params['order_id'], 'send_sample_apply_data_err');
                    return;
                }
            } else {
                return;
            }

            //获取未发送或需要撤销组合
            $standLisModel =  StandLisModel::getInstance();
            $rst = $standLisModel->get_unsend_sample_get_union($params['order_id']);

            log_message('debug', 'param==>' . json_encode_unicode($rst), 'send_sample_apply_data');

            $send_sample_lis = []; //发送
            // $cancel_send_lis = [];//撤销

            if (!empty($rst)) {
                foreach ($rst as $item) {
                    $send_sample_lis[$item['union_barcode']] = $item;
                }
            } else {
                log_message('debug', '发送数据获取为空' . json_encode_unicode($rst), 'send_sample_apply_data');
            }

            foreach ($send_sample_lis as $send_lis_item){
                $this->send_sample_lis([$send_lis_item]);
            }

            $flag = $this->util->unlock_task($params['order_id'],self::lock_key);
            if (!$flag) {
                log_message('error', '锁解发送任务锁失败', 'send_sample_apply_data_err');
            }
        } catch (\Exception $e) {
            log_message('error', '发送失败:'.$e->getMessage(), 'send_sample_apply_data_err');
            $this->util->save_redis_queue($params,self::send_sample_key);
            $flag = $this->util->unlock_task($params['order_id'],self::lock_key);
            if (!$flag) {
                log_message('error', '锁解发送任务锁失败');
            }
        }
    }

    public function send_sample_lis($data, $type = 'NW')
    {
        log_message('debug', '发送：'.json_encode($data), 'send_sample_lis');
        $rs = $this->call_api(\extend\enums\StandapiEnum::SEND_SAMPLE_LIS,['param' => json_encode_unicode($data)]);
        log_message('debug', '返回：'.json_encode_unicode($rs), 'send_sample_lis');

        $standLisModel =  StandLisModel::getInstance();
        if (isset($rs['status']) && $rs['status']) {
            $rst =  $standLisModel->update_sample_send_status($data,$type);
            if (!$rst) {
                log_message('error', '检验发送状态更新失败' . json_encode_unicode($data), 'send_sample_lis_err');
            }
            \models\intf\IntfSendDetailModel::getInstance()->saveSendLog($rs, '标本采集', json_encode($data));
        } else {
            \models\intf\IntfSendDetailModel::getInstance()->saveSendLog($rs, '标本采集', json_encode($data));
            log_message('error', '检验发送失败' . json_encode_unicode($rs), 'send_sample_lis_err');
        }
    }



}