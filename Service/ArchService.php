<?php
/**
 * 建档
 */

namespace App\Service;

use SoapClient;
class ArchService
{
    public function handle($type,$params)
    {
        $xml = "";
        $fun = "";
        switch($type){
            case "reg":
                $xml = $this->handleRegXml($params);
                $fun = "RegPatInfo";
                break;
            case "get":
                $xml = $this->handleGetXml($params);
                $fun = "GetPatInfo";
        }
        
        $result = $this->requestWeb($fun,$xml);
        return $result;
    }
    
    /**
     * 处理查询档案xml
     */
    private function handleGetXml($params){
        $xml = <<<EOF
        <his>
            <request>
                <!-- 号码类型  1病历号,2卡号,3 PatientID  4患者姓名5 身份证号 -->
                <codetype>5</codetype>
                <!-- 号码,它的含义,由上面codetype指定-->
                <code>{{cust_id_card}}</code>
            </request>
        </his>
EOF;
        return strtr($xml, [
            '{{cust_id_card}}' => $params['cust_id_card']
        ]);
    }

    /**
     * 处理注册档案xml
     */
    private function handleRegXml($params)
    {
        $xml = <<<EOF
        <his>
        <request>
            <!--病人卡号-->
            <cardno>{{cust_id_card}}</cardno>
            <!--病人姓名(tdbz=1传体检单位名称)-->
            <hzxm>{{cust_name}}</hzxm>
            <!--性别(男:1,女2,其他3)(tdbz=0必传)-->
            <sex>{{cust_sex}}</sex>
            <!--出生年月(19800101)(tdbz=0必传)-->
            <birth>{{cust_birthday}}</birth>
            <!--医保代码-->
            <ybdm>01</ybdm>
            <!--身份证号(tdbz=0必传)-->
            <sfzh>{{cust_id_card}}</sfzh>
            <!--电话-->
            <lxdh>{{cust_mobile}}</lxdh>
            <!--地址-->
            <lxdz></lxdz>
            <!--邮编-->
            <yzbm></yzbm>
            <!--婚姻状况0未婚,1已婚,2离独,3丧偶,4未知-->
            <hyzk>1</hyzk>
            <!--体检单位名称(tdbz=1是必传)-->
            <tjdwmc></tjdwmc>
            <!--团队标志:0 个人 ;1 团队-->
            <tdbz>0</tdbz>
            <czyh>{{czyh}}</czyh>
        </request>
        </his>
EOF;

        switch ($params['cust_marriage_code'])
        {
            case 1:
                $marriage_code = '0';
                $marriage_name = '未婚';
                break;
            case 2:
                $marriage_code = '1';
                $marriage_name = '已婚';
                break;
            case 3:
                $marriage_code = '3';
                $marriage_name = '丧偶';
                break;
            case 4:
                $marriage_code = '2';
                $marriage_name = '离婚';
                break;
            default:
                $marriage_code = '4';
                $marriage_name = '其它';
        }
        //2022-07-21 修改发送出生日期格式 增加工作人员id
        $params['cust_birthday'] = str_replace('-','',$params['cust_birthday']);
        $params['czyh'] = 12701;

        return strtr($xml, [
            '{{cust_name}}' => $params['cust_name'],
            '{{cust_sex_name}}' => $params['cust_sex_code'] == 1 ? '男' : '女',
            '{{cust_sex}}' => $params['cust_sex_code'],
            '{{cust_birthday}}' => $params['cust_birthday'],
            '{{cust_id_card}}' => $params['cust_id_card'],
            '{{MaritalStatusName}}' => $marriage_code,
            '{{MaritalStatusCode}}' => $marriage_name,
            '{{cust_mobile}}' => $params['cust_mobile'],
            '{{cust_name}' => $params['cust_name'],
            '{{czyh}}' => $params['czyh']
        ]);
    }

    private function requestWeb($fun,$xml){
        $soap = new SoapClient("http://10.166.31.22:8088/WebInterface.asmx?wsdl");
        // 调用函数
        logMessage('调用卫宁接口:' .$fun. ' 请求入参xml: '.PHP_EOL.$xml, 'HIS');
        $result_xml = @$soap->__soapCall('SendEmr', [['msgCode' => $fun, 'sendXml' => $xml]]);
        logMessage('调用卫宁接口:' .$fun. ' 请求回参xml: '.PHP_EOL.$result_xml->SendEmrResult, 'HIS');
        //$result_xml = $soap->SendEmr(['msgCode'=>'RegPatInfo','sendXml'=>$xml]);
        return $result_xml;
    }
    
}
