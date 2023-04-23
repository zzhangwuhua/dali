<?php

namespace services\thirdpart;

use models\standapi\StandApiModel;
use models\standapi\StandPrintModel;
use services\BaseService;

class PrintStatusService extends BaseService
{
    const lock_key = 'SEND_PRINT_APPLY_KEY';
    const send_print_key = 'SEND_PRINT_KEY';

    /**
     * 发送到打印状态
     */
    public function send_print_apply_data()
    {
        $this->load->library('user/util');
        $n = 5;
        $param_arr = [];
        for ($i = 1;$i < $n;$i++){
            if($param_tmp = $this->util->get_redis_queue(self::send_print_key)){//单个登记队列
                $param_tmp = json_decode($param_tmp, true);
                $param_tmp['queue_type'] = 0;
                $param_arr[$param_tmp['order_id']] = $param_tmp;
            }
        }
        log_message('debug', '获取缓存：$params==' . json_encode_unicode($param_arr), 'send_print_apply_data');
        try {
            foreach ($param_arr as $params){
                $this->send_common($params);
            }
        } catch (\Exception $e) {
            log_message('error', '发送任务异常：' . $e->getMessage(), 'send_print_apply_data_err');
        }
    }

    public function send_common($params)
    {
        try{
            if (isset($params['order_id']) && $params['order_id']) {
                $flag = $this->util->lock_task($params['order_id'],self::lock_key);
                if (!$flag) {
                    $this->util->save_redis_queue($params,self::send_print_key);
                    log_message('error', '上一个定时任务执行还在执行中:' . $params['order_id'], 'send_print_apply_data_err');
                    return;
                }
            } else {
                return;
            }

            //获取未发送或需要撤销组合
            $standLisModel =  StandPrintModel::getInstance();
            $rst = $standLisModel->get_unsend_print_get_union($params['order_id']);

            log_message('debug', 'param==>' . json_encode_unicode($rst), 'send_print_apply_data');

            $send_print_lis = []; //发送
            // $cancel_send_lis = [];//撤销

            if (!empty($rst)) {
                foreach ($rst as $item) {
                    $this->send_print_lis([$item]);
                }
            } else {
                log_message('debug', '发送数据获取为空' . json_encode_unicode($rst), 'send_print_apply_data');
            }

            $flag = $this->util->unlock_task($params['order_id'],self::lock_key);
            if (!$flag) {
                log_message('error', '锁解发送任务锁失败', 'send_print_apply_data_err');
            }
        }catch (\Exception $e){
            log_message('error', '发送失败:'.$e->getMessage(), 'send_print_apply_data_err');
            $this->util->save_redis_queue($params,self::send_print_key);
            $flag = $this->util->unlock_task($params['order_id'],self::lock_key);
            if (!$flag) {
                log_message('error', '锁解发送任务锁失败');
            }
        }
    }

    public function send_print_lis($data, $type = 'NW')
    {
        log_message('debug', '发送：'.json_encode($data), 'send_print_lis');
        $rs = $this->call_api(\extend\enums\StandapiEnum::SEND_PRINT_LIS,['param' => json_encode_unicode($data)]);
        log_message('debug', '返回：'.json_encode_unicode($rs), 'send_print_lis');

        $standLisModel =  StandPrintModel::getInstance();
        if (isset($rs['status']) && $rs['status']) {
            $rst =  $standLisModel->update_print_send_status($data);
            if (!$rst) {
                log_message('error', '检验发送状态更新失败' . json_encode_unicode($data), 'send_print_lis_err');
            }
            \models\intf\IntfSendDetailModel::getInstance()->saveSendLog($rs, '打印状态', json_encode($data));
        } else {
            \models\intf\IntfSendDetailModel::getInstance()->saveSendLog($rs, '打印状态', json_encode($data));
            log_message('error', '检验发送失败' . json_encode_unicode($rs), 'send_print_lis_err');
        }
    }


}
