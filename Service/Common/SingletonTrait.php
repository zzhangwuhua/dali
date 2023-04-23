<?php

namespace App\Service\Common;

trait SingletonTrait
{
    private function __construct(){}

    private function __clone(){}

    public function __sleep()
    {
        //重写__sleep方法，将返回置空，防止序列化反序列化获得新的对象
        return [];
    }

    private static $instances = [];

    /**
     * @description
     *
     * @since 2021-07-29
     * @return $this
     */
    public static function getInstance() {
        $class_name = get_called_class();
        if (!isset(self::$instances[$class_name])) {
            self::$instances[$class_name] = new static(); //这里不能new self(),self和static区别
        }
        return self::$instances[$class_name];
    }
}