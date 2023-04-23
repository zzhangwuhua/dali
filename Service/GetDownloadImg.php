<?php

/**
 * 网站图片下载
 */

namespace App\Service;

class GetDownloadImg
{
    private $config = [];       // 项目code => [路径]
    private $field = [];        // 替换字典
    private $code = '';         // 项目代码
    private $img_url = '/';     // 图片服务器地址
    private $flag_url = '';     // 文件服务器目录
    private $timeout = 15;

    public function handle($data)
    {
        logMessage( 'bs请求=======>' . json_encode($data, 1), 'GetDownloadImg');
        $this->initialization($data);
        $img = $this->coreHandle();
        logMessage( '返回bs=======>' . $img, 'GetDownloadImg');
        return $img;
    }

    // 初始化各种变量
    private function initialization($data)
    {
        // 初始化项目代码
        $this->code = $data['item_code'];

        $grab_img_config = config('standapi.grab_img');
        $this->config = $grab_img_config['download_img'];

        // 初始化字典
        $time = (isset($data['order_register_date']) && !empty($data['order_register_date'])) ? strtotime($data['order_register_date']) : time();

        $this->field = [
            '[cust_name]' => $data['cust_name'],
            '[order_code]' => $data['order_code'],
            '[Ymd]' => date('Ymd', $time),
            '[Ymd-H-i]' => date('Ymd-H-i', $time),
            '[Y-m-d]' => date('Y-m-d', $time),
            '[d-m-Y]' => date('d-m-Y', $time),
            '[order_id]' => $data['order_id']
        ];

        // 文件服务器目录
        $this->flag_url = 'mount/';

        // 图片服务器地址
        $this->img_url = config('standapi.upload_root_dir') . $this->flag_url;
    }

    // 核心方法
    private function coreHandle()
    {
        // 项目代码错误
        if (!isset($this->config[$this->code]))
        {
            return '';
        }

        $result = [];

        $imgs = $this->config[$this->code];

        $stream_context = ['http' => ['timeout' => $this->timeout]];

        $today = date('Y-m-d', time());

        // 服务器图片路径
        $server_addr = $this->img_url . $today . '/' . $this->code . '/';
        if (!is_dir($server_addr))
        {
            mkdir($server_addr, 0777, true);
        }

        foreach ($imgs['address'] as $img)
        {
            $img = $this->replaceImgUrl($img);
            $down = function (string $source, string $filename, int $error = 1) use (&$down){
                try {
                    $maxerr = 5;
                    if ($error >= $maxerr) {
                        return [];
                    }
                    $source_size = get_headers($source, true)['Content-Length'] ?: 0;
                    $buffer = fopen($filename, 'w');
                    $ch = curl_init();
                    curl_setopt_array($ch, [
                        CURLOPT_URL => $source,
                        CURLOPT_FILE => $buffer,
                        CURLOPT_AUTOREFERER => true,
                    ]);
                    curl_exec($ch);
                    curl_close($ch);
                    fclose($buffer);
                    // 判断文件大小是否一致
                    if (filesize($filename) != $source_size) {
                        return $down($source, $filename, ++$error);
                    }
                    $mime = preg_split("/\//", mime_content_type($filename));
                    $suffix = $mime[count($mime) - 1];
                    // 判断文件后缀
                    if (2 != count($mime) || !in_array($suffix, ['pdf', 'jpg', 'png', 'jpeg'])) {
                        return [];
                    }
                    $savefile = $filename . '.' . $suffix;
                    @rename($filename, $savefile);
                    if (!empty(error_get_last())) {
                        return $down($source, $filename, ++$error);
                    }
                } catch (\Exception $e) {
                    logMessage( 'error =======>' . $e->getMessage() , 'GetDownloadImg');
                    return $down($source, $filename, ++$error);
                }
                return $savefile;
            };
            logMessage( 'find=======>' . $img , 'GetDownloadImg');
            $final_img_name = $this->getImgName();
            $destination_addr = $server_addr . $final_img_name;
            $img  = $down($img,$destination_addr);
            if(is_array($img)){
                return '';
            }else{
                //获取后缀
                $suffix = pathinfo($img)['extension'];
                if ($suffix == 'pdf'){
                    exec('timeout ' . $this->timeout . ' convert -background white -alpha remove -quality 100 -density 300 ' . $img . ' ' . $destination_addr . '.jpg', $output, $return_var);
                    $true_img = str_replace('/u01/pic', '', $destination_addr);
                    if (file_exists($destination_addr . '.jpg')) {
                        $result[] = $true_img . '.jpg';
                    }else if (file_exists($destination_addr . '-0.jpg')) {
                        // 一个PDF有多张图的情况
                        // 保存第一张
                        $result[] = $true_img . '-0.jpg';
                        // 从第二张图开始查
                        $many_img_num = 1;
                        $many_img_flag = true;
                        while ($many_img_flag) {
                            if (file_exists($destination_addr . '-' . $many_img_num . '.jpg')) {
                                $result[] = $true_img . '-' . $many_img_num . '.jpg';
                                $many_img_num++;
                            } else {
                                $many_img_flag = false;
                            }
                        }
                    }

                }else{
                    $result[] = str_replace('/u01/pic', '', $img);
                }
            }


//            $img = @file_get_contents($img, false, stream_context_create($stream_context));
//            if ($img !== false)
//            {
//                $final_img_name = $this->getImgName();
//                $destination_addr = $server_addr . $final_img_name;
//
//                file_put_contents($destination_addr . $imgs['suffix'], $img);
//
//                if (file_exists($destination_addr . $imgs['suffix']))
//                {
//                    $true_img = $this->flag_url . $today . '/' . $this->code . '/' . $final_img_name;
//
//                    // pdf 和 png 转 jpg
//                    if (in_array($imgs['suffix'], ['.pdf', '.PDF']))
//                    {
//                        exec('timeout ' . $this->timeout . ' convert -quality 100 -density 300 ' . $destination_addr . $imgs['suffix'] . ' ' . $destination_addr . '.jpg', $output, $return_var);
//
//                        if (file_exists($destination_addr . '.jpg'))
//                        {
//                            $result[] = $true_img . '.jpg';
//                        }
//                        else if (file_exists($destination_addr . '-0.jpg'))
//                        {
//                            // 一个PDF有多张图的情况
//
//                            // 保存第一张
//                            $result[] = $true_img . '-0.jpg';
//
//                            // 从第二张图开始查
//                            $many_img_num = 1;
//                            $many_img_flag = true;
//                            while ($many_img_flag)
//                            {
//                                if (file_exists($destination_addr . '-' . $many_img_num . '.jpg'))
//                                {
//                                    $result[] = $true_img . '-' . $many_img_num . '.jpg';
//                                    $many_img_num++;
//                                }
//                                else
//                                {
//                                    $many_img_flag = false;
//                                }
//                            }
//                        }
//                    }
//                    else
//                    {
//                        $result[] = $true_img . $imgs['suffix'];
//                    }
//                }
//                else
//                {
//                    // 少图，直接返回空
//                    logMessage( '图片不存在=======>' . $img , 'GetDownloadImg');
//                    return '';
//                }
//            }
        }
        return empty($result) ? '' : implode(',', $result);
    }

    // 替换得到准确挂载的图片地址
    private function replaceImgUrl($img)
    {
        return strtr($img, $this->field);
    }

    // 生成图片名称
    private function getImgName()
    {
        return rand(10, 1000) . time() . rand(10, 1000);
    }
}
