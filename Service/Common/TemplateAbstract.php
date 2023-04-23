<?php
/**
 * xml模板替换抽象类
 * 模板地址：resources/template/
 * 模板内需要替换的数据用两个花括号包裹，如：{{cust_name}}
 * 仅支持二级循环
 */

namespace App\Service\Common;

abstract class TemplateAbstract implements BaseService
{
    use PlatformTrait;

    // 模板名称
    protected $msg_type;
    // 循环节点模板名称
    protected $loop_template = [];
    // 时间格式
    protected $date_format = [
        'cust_birthday' => 'Ymd',
    ];
    // 性别字典
    protected $cust_sex = [
        0 => [0, '未知'],
        1 => [1, '男'],
        2 => [2, '女'],
    ];
    // 证件字典
    protected $certificates = [];
    // 其他公共字段
    protected $common_field = [
        'common_time' => '',
    ];

    public function handle($params)
    {
        $template_xml_url = resource_path('template/' . $this->msg_type . '.xml');
        
        if (!file_exists($template_xml_url)) {
            return returnFail($this->msg_type . '模板不存在');
        }

        $request_xml = file_get_contents($template_xml_url);

        if (isset($params[0])) {
            $loop_template_xml = [];
            $loop_xml = [];

            if ($this->loop_template) {
                foreach ($this->loop_template as $loop_template_value) {
                    $loop_template_url = resource_path('template/' . $loop_template_value . '.xml');
                    if (!file_exists($template_xml_url)) {
                        return returnFail($loop_template_value . '模板不存在');
                    }
                    $loop_template_xml[$loop_template_value] = file_get_contents($loop_template_url);
                }
            }

            foreach ($params as $union) {
                $body_replace_arr = $this->replace($union, $loop_xml, $loop_template_xml);
            }
            $request_xml = strtr($request_xml, $loop_xml);
        } else {
            $body_replace_arr = $this->replace($params, $loop_xml, []);
        }

        $request_xml = strtr($request_xml, $body_replace_arr);

        if ($this->common_field) {
            $common_field = [];
            foreach ($this->common_field as $field => $value) {
                $common_field['{{' . $field . '}}'] = $value;
            }
            $request_xml = strtr($request_xml, $common_field);
        }

        return $this->requestPlatform($request_xml);
    }

    private function replace($union, &$loop_xml, $loop_template_xml)
    {
        $replace_arr = [];
        $replace_arr['{{cust_age}}'] = howOld($union['cust_birthday']);
        if ($this->date_format) {
            foreach ($this->date_format as $format_field => $format) {
                $union[$format_field] = date($format, strtotime($union[$format_field]));
            }
        }
        if ($this->cust_sex) {
            $cust_sex = $union['cust_sex_code'];
            $union['cust_sex_code'] = $this->cust_sex[$cust_sex][0];
            $union['cust_sex_name'] = $this->cust_sex[$cust_sex][1];
        }
        if ($this->certificates) {
            $cust_type = $union['cust_card_type_code'];
            $union['cust_card_type_code'] = $this->certificates[$cust_type][0];
            $union['cust_card_type_name'] = $this->certificates[$cust_type][1];
        }
        foreach ($union as $union_field => $union_value) {
            $replace_arr['{{' . $union_field . '}}'] = $union_value;
        }
        if ($loop_template_xml) {
            foreach ($loop_template_xml as $loop_template_key => $loop_template_value) {
                $loop_xml['{{' . $loop_template_key . '}}'] = ($loop_xml['{{' . $loop_template_key . '}}'] ?? '') . strtr($loop_template_value, $replace_arr);
            }
        }
        return $replace_arr;
    }
}