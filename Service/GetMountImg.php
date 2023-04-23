<?php


namespace App\Service;


class GetMountImg
{
    private $config = [];       // 项目code => [路径]
    private $field = [];        // 替换字典
    private $code = '';         // 项目代码
    private $mount_url = '';    // 挂载地址
    private $img_url = '/';     // 图片服务器地址
    private $flag_url = '';     // 文件服务器目录
    private $timeout = 5;		// shell命令超时时间

    public function handle($data)
    {
        logMessage( 'bs请求=======>' . json_encode($data, 1), 'GetMountImg');
        $this->initialization($data);
        // 5秒超时
        set_time_limit(5);
        $img = $this->coreHandle();
        logMessage( '返回bs=======>' . $img, 'GetMountImg');
        return $img;
    }

    // 初始化各种变量
    private function initialization($data)
    {
        // 初始化项目代码
        $this->code = $data['item_code'];

        // 获取图片规则配置
        $grab_img_config = config('standapi.grab_img');
        $this->config = $grab_img_config['mount_img'];

        // 初始化字典
        $time = (isset($data['order_register_date']) && !empty($data['order_register_date'])) ? strtotime($data['order_register_date']) : time();
        $this->field = [
            '[cust_name]' => $data['cust_name'],
            '[order_code]' => $data['order_code'],
            '[Ymd]' => date('Ymd', $time),
            '[Ymd-H-i]' => date('Ymd-H-i', $time),
            '[Y-m-d]' => date('Y-m-d', $time),
            '[d]' => date('d', $time),
            // '[d-m-Y]' => date('d-m-Y', $time)
        ];

        // 挂载地址
        $this->mount_url = '/home/hxey/report/';

        // 文件服务器目录
        $this->flag_url = 'mount/';

        // 图片服务器地址
        $this->img_url = config('standapi.upload_root_dir') . $this->flag_url;
    }

    // 数据替换 核心方法
    private function coreHandle()
    {
        // 项目代码错误
        if (!isset($this->config[$this->code]))
        {
            return '';
        }

        $result = [];

        $imgs = $this->config[$this->code];
        foreach ($imgs as $img)
        {
            $img = $this->replaceImgUrl($img);
            logMessage( 'find=======>' . json_encode($img, 1), 'GetMountImg');
            $result[] = $this->copyImg($img);
        }

        $result = $result ? implode(',', $result) : '';
        return trim($result, ',');
    }

    // 替换得到准确挂载的图片地址
    private function replaceImgUrl($img)
    {
        return $this->mount_url . strtr($img, $this->field);
    }

    // 拷贝挂载图片到本地
    private function copyImg($img)
    {
        $today = date('Y-m-d', time());

        $final_img_name = $this->getImgName();

        // 最终图片路径
        $destination_addr = $this->img_url . $today . '/' . $this->code . '/';
        if (!is_dir($destination_addr))
        {
            mkdir($destination_addr, 0777, true);
        }

        try
        {
            if(stripos($img, 'pdf') > -1 || stripos($img, 'png') > -1)
            {
                // 电导仪的pdf转jpg会黑图，所以此项目的图片转png
                $img_suffix = $this->code == 'Z20000000015' ? '.png' : '.jpg';
                $final_img_name .= $img_suffix;
                $destination_addr .= $final_img_name;
                exec('timeout ' . $this->timeout . ' convert -quality 100 -density 150 ' . $img . ' ' . $destination_addr, $output, $return_var);
                // shell_exec('timeout ' . $this->timeout . ' cp ' . $img . ' ' . $destination_addr);
            }
            else
            {
                $img_suffix = $this->getImgSuffix($img);
                $final_img_name .= '.' . $img_suffix;
                $destination_addr .= $final_img_name;
                shell_exec('timeout ' . $this->timeout . ' cp ' . $img . ' ' . $destination_addr);
            }
            // 返回拷贝后的相对路径
            return file_exists($destination_addr) ? $this->flag_url . $today . '/' . $this->code . '/' . $final_img_name : '';
        }
        catch (\Exception $e)
        {
            return '';
        }
    }

    // 生成图片名称
    private function getImgName()
    {
        return rand(10, 1000) . time() . rand(10, 1000);
    }

    // 获取图片后缀
    private function getImgSuffix($img)
    {
        return pathinfo($img,PATHINFO_EXTENSION);
    }

    // 删除图片
    public function removeImg($imgs)
    {
        foreach ($imgs as $img)
        {
            $img = $this->img_url . $img;
            logMessage( $img, 'removeImg');
            var_dump(file_exists($img));
            if (file_exists($img))
            {

                // unlink($img);
            }
        }
    }
}
