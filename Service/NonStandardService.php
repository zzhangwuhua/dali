<?php
/**
 * 非标
 */

namespace App\Service;


use App\Service\Common\BaseService;
use App\Service\Common\PlatformTrait;
use Illuminate\Support\Facades\DB;
use Mockery\Exception;

class NonStandardService implements BaseService
{

    public function handle($params)
    {

        $response = $this->apply($params);
        return $response['status'] == false
            ?
            [
                'status' => FAIL_CODE,
                'data' => [],
                "msg" => $response['msg']
            ]
            :
            [
                'status' => SUCCESS_CODE,
                // 'data' => [['union_request_no' => $param['union_request_no'], 'execute_flag' => 0]],
                'data' => [],
                "msg" => $response['msg']
            ];

    }

    public function reports($params){
        if($params['type']=='c13'){
            $this->c13report();
        }
    }

    // 申请发送
    private function apply($param)
    {
        if($param['dest_name'] == 'C13'||$param['dest_name'] == 'C14'){
            return $this->c13apply($param);
        }
    }
    //碳十三申请跟撤销
    private function c13apply($item){
        $data['barcode'] = $item['medical_record_no'];
        $data['name'] = $item['cust_name'];
        $data['age'] = $this->birthday($item['cust_birthday']);
        $data['sex'] = $item['cust_sex'];
        $data['sq_time'] = $item['apply_time'];
        $data['union_request_no'] = $item['union_request_no'];
        $data['order_code'] = $item['order_code'];
        $data['status'] = 2;
        logInfo('非标申请 post -> ', $data, 'Non');
        if($item['dest_name']=='C13'){
            $db = DB::table("c13_apply");
        }else if($item['dest_name']=='C14'){
            $db = DB::table("c14_apply");
        }

        $results = $db->where(["union_request_no"=>$data['union_request_no'],'order_code'=>$data['order_code']])->first();
        if($item['apply_flag']=="NW"){
            try {
                empty($results)?$db->insert($data):$db->where(["id"=>$results->id])->update($data);
                return ['status'=>true,'msg'=>'申请成功'];
            }catch (\Exception $e){
                logMessage('[错误]' . $e->getMessage(), 'Non_error');
                return ['status'=>false,'msg'=>'写入失败'];
            }
        }else if($item['apply_flag']=="CA"){
            try {
                $db->where(["id"=>$results->id])->delete();
                return ['status'=>true,'msg'=>'撤销成功'];
            }catch (\Exception $e){
                logMessage('[错误]' . $e->getMessage(), 'Non_error');
                return ['status'=>false,'msg'=>'撤销失败'];
            }
        }


    }

    private function birthday($birthday){
        $age = strtotime($birthday);
        if($age === false){
            return false;
        }
        list($y1,$m1,$d1) = explode("-",date("Y-m-d",$age));
        $now = strtotime("now");
        list($y2,$m2,$d2) = explode("-",date("Y-m-d",$now));
        $age = $y2 - $y1;
        if((int)($m2.$d2) < (int)($m1.$d1))
            $age -= 1;
        return $age;
    }

    private function c13report(){
        $db = DB::table("c13_report");
        $results = $db->where(["status"=>2])->get();

        if(!empty($results)){
            foreach($results as $report){
                $apply = DB::table("c13_apply")->where(['barcode'=>$report->barcode])->first();
                if($apply->union_request_no!=''){
                    $data['union_request_no'] = $apply->union_request_no;
                    $data['exe_dept_code'] = '49';
                    $data['exe_dept_name'] = '呼气实验室';
                    $data['examiner_id'] = '306';
                    $data['examiner_name'] = '李嵘';
                    $data['examiner_time'] = $report->jc_time;
                    $data['review_status'] = 1;
                    $data['reviewer_id'] = '306';
                    $data['reviewer_name'] = '李嵘';
                    $data['reviewer_time'] = $report->jc_time;
                    $data['check_desc'] = '';
                    $data['check_conclusion'] = '检测结果：DOB=' . $report->num.' ; '.$report->value;
                    $data['report_data'] = 'http://192.168.242.117:8086/c13/' . $report->barcode . '.pdf';

                    logInfo('非标结果 post -> ', $data, 'ReportNon');
//                    var_dump($data);
                    $res = $this->report_tobs([$data],$report->id);
//                    $res?$db->where(['id'=>$report->id])->update(['status'=>1]):'';
                }
            }
        }
    }

    private function report_tobs($data,$id){
        $res = toBS('check_report', $data);
        if($res['status']==true){
            DB::table("c13_report")->where(['id'=>$id])->update(['status'=>1]);
        }else{

        }
    }


}
