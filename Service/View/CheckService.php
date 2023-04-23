<?php


namespace App\Service\View;


use App\Models\PacsApply;
use App\Models\PacsReport;
use App\Models\InterfaceLog;
use App\Service\OracleService;
use Illuminate\Support\Facades\DB;

class CheckService implements BaseInterface
{

    public function handle($data)
    {
        if ($data[0]['apply_flag'] == 'NW')
        {
            $result = $this->apply($data);
        }
        else
        {
            $result = $this->cancel($data);
        }
        return $result;
    }

    /**
     * 状态抓取
     */
    public static function status()
    {
        $pacs_apply_result = PacsApply::select(['*'])
            ->where('exec_status_read', '=', 0)
            ->where('apply_type', '=', 'NW')
            ->whereIn('exec_status', [1,2])
            ->get()
            ->toArray();

        if ($pacs_apply_result)
        {
            $status = [];
            foreach ($pacs_apply_result as $apply)
            {
                $status = [
                    'union_request_no' => $apply['union_request_no'],
                    'union_barcode' => '',
                    'apply_no' => '',
                    'status' => 'E',
                    'comments' => '',
                ];
                PacsApply::where('id', $apply['id'])->update(['exec_status_read' => 1]);
                InterfaceLog::saveLog(microtime(), 'check_status', $status);
                toBS('perform_status', $status);
            }
        }
    }

    /**
     * 抓取心电图报告
     */
    public static function ecgReport()
    {

        $rptSql = "select distinct ApplyCode as union_request_no from dbo.View_TJ where IsPrint='' and APPLYCODE='2212091456000002'";
        $updateSql = "update dbo.View_TJ set IsPrint=1 where ApplyCode=':union_request_no' and IsPrint=''";
        //查询坐标报告数据
        $conn = self::getDB();
        $stmt = $conn->prepare($rptSql);
        $stmt->execute();
        $rpt1 = $stmt->fetchAll();
        logInfo('1aweewe',$rpt1, 'pacsecg6666');
        if ($rpt1) {
            // 分批回传
            foreach ($rpt1 as $item) {
                // $dataSql = str_replace(':union_request_no', $item['union_request_no'], $dataSql);
                $dataSql = "select ApplyCode as union_request_no,'' as union_examiner_id,RepCreator as union_examiner_name,RepCreateDate as union_examiner_time,'' as union_reviewer_id,RepAudit as union_reviewer_name,RepAuditTime as union_reviewer_time,CheckProjectCode as union_code,CheckProject as union_name,RepDescription as check_desc,'' as abnormal_flags,RepDiagnose as check_conclusion,RepPath as report_data from dbo.View_TJ where ApplyCode = '".$item['union_request_no']."'";
                $conn->query('set names utf8;');
                $stmt = $conn->prepare($dataSql);
                $stmt->execute();
                $data = $stmt->fetchAll();

                if (!empty($data[0]['check_desc'])) {
                    $data[0]['check_desc'] =  mb_convert_encoding($data[0]['check_desc'],'UTF-8');
                    $data[0]['check_conclusion'] = mb_convert_encoding($data[0]['check_conclusion'],'UTF-8');
                }

                logInfo('1',$data, 'pacs');
                InterfaceLog::saveLog(microtime(), 'pacs_report', $data);
                //return false;
                $res = toBS('check_report', $data);
                //logInfo('1',$res, 'pacs5656');
                // 更新读取状态
                $updateSql = str_replace(':union_request_no', $item['union_request_no'], $updateSql);
                $stmt = $conn->prepare($updateSql);
                $stmt->execute();
            }
        }

        return [];
    }


    public static function getDB(){

        $server_name = env('SQL_SERVER_NAME');//服务名称
        $user_name = env('DB_ECG_USERNAME');// sqlserver用户名
        $password = env('DB_ECG_PASSWORD');// sqlserver密码
        try {
            $conn = new \PDO("odbc:{$server_name}", $user_name, $password);
            $conn->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
            $conn->setAttribute(\PDO::ATTR_EMULATE_PREPARES, false);
        } catch (\Throwable $th) {

            return ['status' => false, 'error' => $th->getMessage()];

        }

        return $conn;

    }

    /**
     * 报告抓取
     */
    public static function report()
    {
        $config = config('standapi.pacs');
        $oracle = new OracleService($config['user'], $config['password'], $config['host']);

        $sql = "SELECT UNION_REPUEST_NO
    ,RIS_NO
	,EXS_DEPT_CODE
	,EXS_DEPT_NAME
	,EXAMINER_ID
	,EXAMINER_NAME
	,to_char(EXAMINER_TIME,'yyyy-mm-dd hh24:mi:ss') as EXAMINER_TIME
	,REVIER_STATUS
	,INI_REVIEWER_ID
	,INI_REVIEWER_NAME
	,to_char(INI_REVIEWER_TIM,'yyyy-mm-dd hh24:mi:ss') as INI_REVIEWER_TIM
	,REVIEWER_ID
	,REVIEWER_NAME
	,to_char(REVIEWER_TIME,'yyyy-mm-dd hh24:mi:ss') as REVIEWER_TIME
	,DESCRIPTION
	,IMPRESSION
	,REPORT_DATA
	,LASTSAVETIME
FROM SSRIS.VIEW_PACS_GETRFEPORT WHERE UNION_REPUEST_NO='2212071330000002'";

        $hisData = $oracle->oracle_fetch_array($sql);

        if (empty($hisData)){
            return;
        }
        //$data = array_chunk($hisData, 10);

        logInfo('检查申请 post5 -> ', $hisData, 'CheckReport');

        //return false;
        foreach ($hisData as $item){

            $reports[] = [
                'union_request_no' => $item["UNION_REPUEST_NO"],
                'examiner_id' =>$item['EXAMINER_ID'] ?? null,
                'examiner_name' => $item['EXAMINER_NAME'] ?? null,
                'examiner_time' => $item['EXAMINER_TIME'],
                'reviewer_id' => $item['REVIEWER_ID'],
                'reviewer_name' => $item['REVIEWER_NAME'],
                'reviewer_time' => $item['INI_REVIEWER_TIM'],
                'check_desc' => $item['DESCRIPTION'] ?? null,
                'check_conclusion' => $item['IMPRESSION'] ?? null,
                'report_data' => $item['REPORT_DATA'],
            ];

        logInfo('检查申请 post3 -> ', $reports, 'CheckReport8888');
        if (!empty($reports)){
            toBS('check_report', $reports);
        }
        $query = "BEGIN SSRIS.SETAPPLYFLAGTJ('{$item['RIS_NO']}'); END;";
        $ret = $oracle->oracle_query($query);
        logInfo('检查申请 post3 -> ', [$ret], 'CheckReport9999');
        InterfaceLog::saveLog(microtime(), 'check_report', $reports);
    }
//        $reports = PacsReport::select([
//            'id',
//            'union_request_no',
//            'examiner_id',
//            'examiner_name',
//            'examiner_time',
//            'reviewer_id',
//            'reviewer_name',
//            'reviewer_time',
//            'check_desc',
//            'check_conclusion',
//            'report_data',
//        ])
//            ->where('exec_status_read', '=', 0)
//            ->where('reviewer_time', '>=', date('Y-m-d'))
//            ->get()
//            ->toArray();

//        if ($reports)
//        {
//            $ids = array_column($reports, 'id');
//            PacsReport::whereIn('id', $ids)->update(['exec_status_read' => 1]);
//            InterfaceLog::saveLog(microtime(), 'check_report', $reports);
//            toBS('check_report', $reports);
//        }
    }

    /**
     * 申请
     * @param $data
     * @return \Illuminate\Http\JsonResponse
     */
    private function apply($data)
    {
        $insert_arr = [];
        $time = date('Y-m-d H:i:s', time());
        foreach ($data as $union)
        {
            $insert_arr[] = [
                'apply_no' => $union['apply_no'],
                'order_code' => $union['order_code'],
                'patient_id' => $union['medical_record_no'],
                'apply_type' => 'NW',
                'cust_name' => $union['cust_name'],
                'cust_sex' => $union['cust_sex'],
                'cust_id_card' => $union['cust_id_card'],
                'cust_birthday' => $union['cust_birthday'],
                'cust_type' => $union['cust_type'],
                'cust_mobile' => $union['cust_mobile'],
                'cust_marriage_code' => $union['cust_marriage_code'],
                'exam_type' => $union['exam_type'],
                'union_request_no' => $union['union_request_no'],
                'union_code' => $union['union_code'],
                'union_name' => $union['his_union_name'],
                'union_fee' => $union['union_fee'],
                'disc_rate' => $union['disc_rate'],
                'union_pay_fee' => $union['union_real_fee'],
                'union_barcode' => $union['union_barcode'],
                'body_code' => $union['his_union_body_code'],
                'body_name' => $union['his_union_body_site'],
                'method_code' => $union['his_union_method_code'],
                'method_name' => $union['his_union_method'] ?? '',
                'exe_dept_code' => $union['exe_dept_code'],
                'exe_dept_name' => $union['exe_dept_name'],
                'dest_code' => $union['dest_code'],
                'dest_name' => $union['dest_name'],
                'applicant_id' => $union['operator_id'],
                'applicant_name' => $union['operator_name'],
                'apply_desc' => '',
                'apply_dept_code' => $union['dept_code'],
                'apply_dept_name' => $union['dept_name'],
                'exec_status' => 0,
                'exec_status_read' => 0,
                'create_time' => $time,
            ];
        }
        $result = PacsApply::insert($insert_arr);
        return $result ? ['status' => true] : ['status' => false, 'msg' => '发送失败'];
    }

    /**
     * 撤销
     * @param $data
     * @return \Illuminate\Http\JsonResponse
     */
    private function cancel($data)
    {
        $union_request_nos = array_column($data, 'union_request_no');

        // 检查是否有已执行的项目
        $exec_union = PacsApply::select()->whereIn('union_request_no', $union_request_nos)
            ->where('apply_type', '=', 'NW')
            ->where('exec_status', '=', 1)
            ->get()
            ->toArray();

        if (count($exec_union) > 0)
        {
            $unions = implode(',', array_column($exec_union, 'union_name'));
            return ['status' => false, 'msg' => $unions . ' 已执行，不允许撤销'];
        }

        PacsApply::whereIn('union_request_no', $union_request_nos)
            ->where('apply_type', '=', 'NW')
            ->where('exec_status', '=', 0)
            ->update([
                'apply_type' => 'CA',
                'cancel_time' => date('Y-m-d H:i:s'),
            ]);
        return ['status' => true];
    }
}
