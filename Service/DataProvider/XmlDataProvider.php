<?php

namespace App\Service\DataProvider;

use App\Service\Common\SingletonTrait;
use App\Service\Common\BasePlatformAbstract;
use App\Service\Common\BaseDataProviderInterface;

class XmlDataProvider implements BaseDataProviderInterface
{
    use SingletonTrait;
    
    public function encryptData($params)
    {
        switch ($params['message_code']) {
            case BasePlatformAbstract::ARCH_QUERY:
            case BasePlatformAbstract::ARCH_CREATE:
            case BasePlatformAbstract::ARCH_UPDATE:
            case BasePlatformAbstract::FEE_APPLY:
            case BasePlatformAbstract::APPLY_CANCEL:
            case BasePlatformAbstract::LIS_APPLY:
            case BasePlatformAbstract::CHECK_APPLY:
                $xml = $this->arr2xml($params['data']);
                break;
            case BasePlatformAbstract::RESPONSE:
                $xml = $this->resp2xml($params['data']);
                break;
            default:
                $xml = '';
        }

        return $xml;
    }

    public function decryptData($params)
    {
        $objectxml = simplexml_load_string($params, 'SimpleXMLElement', LIBXML_NOCDATA); //将文件转换成对象
        $xmljson = json_encode($objectxml); //将对象转换个JSON
        $xmlarray = json_decode($xmljson, true); //将json转换成数组
        return $xmlarray;
    }

    private function arr2xml($arr, $flag = true) {
        $xml = $flag ? "<Param>" : '';
        foreach ($arr as $key => $val) {
            if (is_array($val)) {
                $tmpXml = $this->arr2xml($val, false);
                if (!is_int($key)) {
                    $xml .= "<$key>$tmpXml</$key>";
                } else {
                    $xml .= "<item>$tmpXml</item>";
                }
            }else{
                if (!is_int($key)) {
                    $xml .= "<$key>$val</$key>";
                }else{
                    $xml .= "<item>$val</item>";
                }
            }
        }
        
        $xml .= ($flag) ? "</Param>" : '';
        return $xml;
    }

    private function resp2xml($arr)
    {
        $xml = '';
        foreach ($arr as $key => $val) {
            if (is_array($val)) {
                $tmpXml = $this->resp2xml($val);
                $xml .= "<$key>$tmpXml</$key>";
            } else {
                $xml .= "<$key>$val</$key>";
            }
        }
        return $xml;
    }
}