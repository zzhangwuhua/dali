<?php

namespace services\thirdpart;

use extend\enums\ParameterEnum;
use extend\utils\Image;
use models\biz\OrderPackageUnionModel;
use models\intf\IntfOrderUnionModel;
use models\priv\ParameterModel;
use services\BaseService;


class LWPIMSService extends BaseService
{
    const LWPIMSURL = 'http://10.193.2.170:61223/api/HospitalDocking/GetReportData?paramType=4&paramValue=';
   # const KM_TOKEN = '';
    public function __construct() {

      //  ini_set('mssql.textlimit',4294967296);
      //  ini_set('mssql.textsize',4294967296);
        parent::__construct();
    }


    public function getLWPIMSReport($union_barcode = '')
    {
        $datetime = date('Y-m-d H:i:s',strtotime('-14 day'));
      //  $dept_code_info = ParameterModel::getByParamCodeJson(ParameterEnum::SPECIAL_DEPT_CODE);
        $query = $this->db->select('DISTINCT o.order_code,ou.union_request_no',false)
            ->from('biz_order_package_union opu')
            ->join('biz_order o','o.id = opu.order_id')
            ->join('intf_order_union ou' ,'ou.order_id = o.id and ou.order_pkg_union_id = opu.id')
            ->join('intf_union_relation ur','ur.ipeis_union_code = opu.union_code')
            ->where('o.order_status_code',2)
            ->where('ou.delete_flag = 0 and ou.status = 0')
            ->where('opu.delete_flag = 0 and opu.status = 0')
            ->where('opu.order_union_option != -1')
            ->where('opu.pay_flag = 1')
            ->where('opu.union_exam_flag = 0')
            ->where('ou.report_no is null')
            ->where('ur.dept_code','LWPIMS')
            ->where('o.order_register_date >',$datetime);
         //   ->not_like('o.order_name','测试');

        if ($union_barcode) {
            $query->where('ou.union_barcode',$union_barcode);
        }

        $query->limit(100);
        $list = $query->get()->result_array();

        log_message('debug',$this->db->last_query(),'getLWPIMSReport');

        $rpt_img_path = config_item('rpt_img_path').'LWPIMS';
        $pic_url = config_item('pic_url');
        foreach ($list as $item) {
           $result =  file_get_contents(self::LWPIMSURL.$item['order_code']);
           $arr = json_decode($result,true);
           if (!empty($arr['ResultType']) && $arr['ResultType'] == 2) {
               log_message('debug',json_encode_unicode($arr),'getLWPIMSReport_err');
               continue;
           }
            foreach ($arr as $report) {
                $pic_url = str_replace(config_item('rpt_img_path'),$pic_url,Image::urlOfPDFToImage($report['FileUrlPdf'],$rpt_img_path));
                $this->handle($report,$pic_url);
           }
        }
    }

    public function handle($result,$pic_url)
    {

       $doctor_name =  $result['DoctorNameReport'] ?? ($result['DoctorNamesReview'] ?? $result['DoctorNameIssued']);
       $report_time = $result['FirstReportTime'] ? date('Y-m-d H:i:s', strtotime($result['FirstReportTime'])) : date('Y-m-d H:i:s');
        $report = [
            'report_no' => $result['Id'],
            'union_request_no' => $result['HisSheetId'],
            'exe_dept_code' => '',
            'exe_dept_name' => '',
            'examiner_id' => '',
            'examiner_name' => $doctor_name,
            'examiner_time' => $report_time,
            'reviewer_id' => '',
            'reviewer_name' => $doctor_name,
            'reviewer_time' => $report_time,
            'check_desc' => '',
            'check_conclusion' => $result['Diagnosis'],
            'report_data' => $pic_url,//$result['FileUrlPdf']
        ];
        $rs = HisService::getInstance()->reportBack([$report],'F004');
        if (isset($rs['error'])) {
            log_message('debug',$rs,'getLWPIMSReport_err');
        }
        IntfOrderUnionModel::getInstance()->updateIntfo([['report_no' => $report['report_no'],'fee_request_code' => $report['union_request_no']]],'fee_request_code');
    }

    public function syncStatus($fee_request_code,$order_pkg_union_id)
    {
        try {
            $conn = $this->get_odbc_connection('km_pro', 'sa', 'kingmed');
            if (is_array($conn)) {
                log_message('debug', '链接数据库失败' . $conn['error'], 'getKMReport_err');
                print_r($conn['error']);exit;
                return;
            }

            $gc = $conn->prepare("SELECT * FROM PathologyOfFx.dbo.v_TJ_Status WHERE HisSheetId = '{$fee_request_code}'");
            $gc->execute();
            $result = $gc->fetchAll(\PDO::FETCH_ASSOC);
            if (!$result) {
                return;
            }

            foreach ($result as $item) {
                if ($item['StatusName'] == '已接收') {
                    OrderPackageUnionModel::getInstance()->db->query('update biz_order_package_union set union_execute_flag = 1 where id='.$order_pkg_union_id);
                    break;
                }
            }

        } catch (\Exception $e) {
            log_message('debug',$e->getMessage(),'getLWPIMSReport_err');
        }
    }
}