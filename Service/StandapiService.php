<?php

namespace App\Service;

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

class StandapiService
{
    private $supported_formats = [];
    private $pic_url = '';

    public function __construct()
    {
        $this->supported_formats = ['jpg', 'jpeg', 'png', 'pdf'];
        $this->pic_url = config('standapi.pic_url');
    }

    public function handleImgs(Request $request): string
    {
        try {
            $img_url_arr = [];

            if ($request->has('imgs')) {
                $images = $request->file('imgs');

                if (is_array($images)) {
                    foreach ($images as $image) {
                        $img_url_arr[] = $this->saveImg($image);
                    }
                } else {
                    $img_url_arr[] = $this->saveImg($images);
                }

                $img_url_arr = implode(',', $img_url_arr);
            } else {
                $img_url_arr = '';
            }
            return $img_url_arr;
        } catch (\Exception $exception) {
            return returnFail($exception->getMessage());
        }
    }
    public function saveImg(UploadedFile $image): string
    {
        $upload_root_dir = config('standapi.upload_root_dir');

        $extension = $image->getClientOriginalExtension();

        if (!in_array(strtolower($extension), $this->supported_formats)) {
            throw new \Exception('上传图片格式错误，仅支持' . implode(',', $this->supported_formats));
        }

        $folder = 'standapi' . '/' . date('Y-m-d') . '/';

        $file_name = time() . '_' . Str::random(10) . '.' . $extension;

        $image->move($upload_root_dir . $folder, $file_name);

        return $this->pic_url . $folder . $file_name;
    }
}
