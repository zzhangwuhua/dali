<?php

namespace services\thirdpart;

use services\BaseService;

class ProvinceMedicalService extends BaseService
{
    /**
     * @description
     * $reportData = [
     *      'userInfo' => [
     *          'name' => '', // 姓名
     *          'idcardType' => '', // 身份证件类别
     *          'idcardCode' => '', // 身份证件号码
     *          'sexCode' => '', // 性别编码
     *          'birthday' => '', // 出生日期
     *          'telPhone' => '', // 联系电话
     *          'emergencyContactPerson' => '', // 紧急联系人
     *          'emergencyContactPhone' => '', // 紧急联系电话
     *          'jobCode' => '', // 工种编码
     *          'otherJobName' => '', // 其他工种名称 当工种编码为其他工种时必填
     *          'radiationType' => '', // 放射工种编码
     *          'maritalStatusCode' => '', // 婚姻状态编码 
     *      ],// 用户信息
     *      'empInfo' => [
     *          'creditCode' => '',// 统一社会信用代码
     *          'employerName' => '',// 用人单位名称
     *          'economicTypeCode' => '',// 经济类型编码
     *          'industryCategoryCode' => '',// 行业类别编码
     *          'enterpriseSizeCode' => '',// 企业规模编码
     *          'areaCode' => '',// 所属地区编码
     *          'address' => '',// 通讯地址
     *          'addressZipCode' => '',// 邮政编码
     *          'contactPerson' => '',// 用人单位联系人
     *          'employerPhone' => '',// 用人单位联系电话
     *          'areaName' => '',// 用人单位所在区名称
     *      ],// 用人单位信息
     *      'cardInfo' => [
     *          'code' => '', //职业健康档案编号  该报告在该医院的唯一编号
     *          'type' => '', //职业健康档案类别  101 职业健康体检个案卡 201 放射卫生体检个案卡
     *          'checkType' => '', //检查类型
     *          'bodyCheckType' => '', //体检类型编码
     *          'previousCardId' => '', //复检对应上次的职业健康档案编号  如检查类型为复查则必填
     *          'checkTime' => '', //体检时间
     *          'writePerson' => '', //填表人名称
     *          'writePersonTel' => '', //填表人电话
     *          'writeDate' => '', //填表日期
     *          'reportOrgName' => '', //填表单位名称
     *          'reportTime' => '', //体检报告时间
     *          'checkResultCode' => '', //主检结论
     *          'suggest' => '', //主检建议
     *          'checkDoctor' => '', //主检医生
     *          'monitorTypeCode' => '', //监测类型代码
     *          'reportUnit' => '', //报告单位名称
     *          'reportPerson' => '', //报告人姓名
     *          'reportPersonTel' => '', //报告人联系电话
     *      ],// 职业健康档案信息
     *      'contactHazardFactorList' => [
     *          [
     *              'hazardCode' => '', //危害因素编码
     *              'otherHazardName' => '', //其他危害因素具体名称 危害因素选择其他时，该项必填
     *          ],//接触的危害因素
     *      ],// 接触的危害因素列表
     *      'hazardFactorList' => [
     *          [
     *              'hazardCode' => '', //危害因素编码
     *              'otherHazardName' => '', //其他危害因素具体名称 危害因素选择其他时，该项必填
     *              'hazardStartDate' => '', //开始接害日期
     *              'hazardYear' => '', //接触所监测危害因素工龄年
     *              'hazardMonth' => '', //接触所监测危害因素工龄月
     *          ], // 体检危害因素
     *      ],// 体检危害因素列表
     *      'itemList' => [
     *          [
     *              'itemId' => '', //体检项目编号
     *              'otherItemName' => '', //其他项目名称 当体检项目编号为其他时该项必填
     *              'itemGroupName' => '', //项目组合名称
     *              'department' => '', //检查科室
     *              'result' => '', //检查项目结果
     *              'type' => '', //检查结果类别编码
     *              'unit' => '', //计量单位
     *              'max' => '', //参考范围最大值
     *              'min' => '', //参考范围最小值
     *              'checkResult' => '', //检查项目结论
     *              'mark' => '', //合格标记
     *              'checkDate' => '', //检查日期
     *              'checkDoctor' => '', //检查医生
     *          ],//检查项目
     *      ],// 检查项目列表
     *     'diagnosisList' => [
     *          [
     *              'conclusion' => '',// 体检结论编码
     *              'repeatItemList' => [
     *                  [
     *                      'repeatItemId' => '',// 需复查的检查项目编码
     *                      'otherItemName' => '',// 需复查的其他检查项目名称 当体检项目编号为其他时该项必填
     *                  ],//复查项目
     *              ],// 复查项目列表
     *              'cdtList' => [
     *                  [
     *                      'cdtHazardCode' => '', //危害因素编码
     *                      'cdtOtherHazardName' => '', //其他危害因素具体名称
     *                      'cdtId' => '', //职业禁忌证编
     *                  ],//职业禁忌证
     *               ],// 职业禁忌证列表
     *              'sptList' => [
     *                  [
     *                      'sptHazardCode' => '', //危害因素编码
     *                      'sptOtherHazardName' => '', //其他危害因素具体名称
     *                      'sptId' => '', //疑似职业病编码
     *                  ], // 疑似职业病
     *              ],// 疑似职业病列表
     *              'otherList' => [
     *                  '', //其他疾病名称
     *              ],// 其他疾病列表
     *          ], //诊断结论
     *     ],// 诊断结论列表
     *     'auditInfo' => [], // 留空
     *     'healthSurvey' => [], // 留空
     * ];
     * 
     * $employerData = [
     *      'creditCode' => '',//统一社会信用代码
     *      'employerName' => '',// 用人单位名称
     *      'areaCode' => '',// 所属地区编码
     *      'economicTypeCode' => '',// 经济类型编码
     *      'industryCategoryCode' => '',// 所属行业编码
     *      'enterpriseSizeCode' => '',// 企业规模编码
     *      'address' => '',// 地址
     *      'addressZipCode' => '',// 邮编
     *      'contactPerson' => '',// 单位联系人
     *      'contactPhone' => '',// 单位联系电话
     *      'isSubsidiary' => '',// 是否子公司
     *      'secondEmployerCode' => '',// 二级公司代码
     *      'createAreaCode' => '',// 创建地区编码
     *      'writeUnit' => '',// 填表单位名称
     *      'writePerson' => '',// 填表人名称
     *      'writePersonTel' => '',// 填表人电话
     *      'writeDate' => '',// 填表日期
     *      'reportUnit' => '',// 报告单位名称
     *      'reportPerson' => '',// 报告人姓名
     *      'reportPersonTel' => '',// 报告人电话
     *      'reportDate' => '',// 报告日期
     * ];
     * 
     *
     * @since 2021-05-27
     * @param array $data
     * @return mixed
     */
    public function occu_disease_upload($reportData) {
        try {
            if (empty($reportData)) return ['error' => '上传数据为空！'];
            $data['reportData'] = json_encode_unicode($reportData);
            return self::call_healthfront('OccudiseaseUpload/reported_data', $data);
        }catch(\Exception $e) {
            return ['msg' => $e->getMessage(), 'status' => 0];
        }
    }

    /**
     * @description
     * 访问前置机
     *
     * @since 2021-05-27
     * @param string $route
     * @param array $params
     * @throws Exception
     * @return mixed
     */
    public static function call_healthfront($route, $params) {
        $front_url = config_item('front_url');
        if (!$front_url) throw new \Exception("未配置前置机地址！");
        log_msg("[访问前置机 {$route} 参数]".json_encode_unicode($params));
        $res = \CI_MyApi::excute($front_url.$route, $params, 'POST');
        log_msg("[{$route} 返回数据]" . json_encode_unicode($res));
        if (!$res['status']) throw new \Exception("接口返回错误！".$res['msg'] ?? '');
        return $res; // ['msg'=>"成功",'status'=>1,'result'=>"success"]  ['msg'=>$e->getMessage(),'status'=>0,'result'=>"error"]
    }
}
