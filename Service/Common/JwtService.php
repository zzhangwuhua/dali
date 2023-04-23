<?php


namespace App\Service\Common;

use App\Utils\Verify\Jwt;
use App\Utils\Verify\Rsa;

class JwtService
{
    /**
     * 生成token
     * @param $exp
     * @return bool|string
     */
    public static function getToken($exp) {
        $payload = [
            'iss' => 'ds_api_center',
            'iat' => time(),
            'exp' => time() + $exp,
            'sub' => strtolower(config('standapi.hospital_key')),
            'jti' => md5(uniqid('JWT') . time()),
        ];

        $token = Jwt::getToken($payload);
        return $token;
    }

    /**
     * 验证token
     * @param $hospital_key
     * @param $auth_token
     * @return mixed
     * @throws \Exception
     */
    public static function verifyToken($hospital_key, $auth_token)
    {
        if (strtolower($hospital_key) !== strtolower(config('standapi.hospital_key')))
        {
            throw new \Exception("体检医院{$hospital_key}与接口医院" . strtolower(config('standapi.hospital_key')) . "不一致！");
        }
        return Jwt::verifyToken($auth_token);
    }

    /**
     * 验证密码
     * @param $hospital_key // 医院key
     * @param $password // 密码
     * @return bool
     * @throws \Exception
     */
    public static function verifyPassword($hospital_key, $password) {
        if (strtolower($hospital_key) !== strtolower(config('standapi.hospital_key')))
        {
            throw new \Exception("体检医院{$hospital_key}与接口医院" . strtolower(config('standapi.hospital_key')) . "不一致！");
        }

        $verify_url = dirname(dirname(dirname(__FILE__))) . '/Utils/Verify/';
        $private_key = $verify_url . 'api_private_key.pem';
        $public_key = '';
        $rsa = new Rsa($private_key, $public_key);
        $decryption_data = $rsa->privDecrypt($password);

        if (!is_array($decryption_data))
        {
            $decryption_data = json_decode($decryption_data, true);
        }

        if ($decryption_data && $decryption_data['api_secret'] != config('standapi.api_secret'))
        {
            throw new \Exception('体检系统密码不正确，token获取失败！');
        }

        return true;
    }

    /**
     * 获取签名
     * @return string
     * @throws \Exception
     */
    public static function getSignature() {
        $verify_url = dirname(dirname(dirname(__FILE__))) . '/Utils/Verify/';
        $private_key = $verify_url . 'api_private_key.pem';
        $public_key = '';
        $rsa = new Rsa($private_key, $public_key);
        $signStr = $rsa->privEncrypt(json_encode(['api_secret' => config('standapi.api_secret')], 256));

        if (!$signStr)
        {
            throw new \Exception('获取签名失败！');
        }

        return $signStr;
    }
}