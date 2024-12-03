<?php
namespace Woocan\Core;

/**
 * Class Midware
 * @package Woocan\Core
 */
class Midware
{
    /**
     * @var array 默认中间件
     *      ApiStats, API调用统计
     *      Idempotent, 幂等性控制器
     *      XhprofStats, xhprof性能节点统计
     *      RequestLimit, 流量限制
     */
    private static $avaiableWare = [];

    private static $ready = false;

    /**
     * 初始化每个中间件
     */
    public static function initial()
    {
        if (!self::$ready && ($avaibles = C('midware')) !== null) {
            foreach ($avaibles as $wareName => $config) {
                $instance = Factory::getInstance('\\Woocan\\Midware\\'. $wareName);
                $instance->initial($config);

                self::$avaiableWare[] = $wareName;
            }
            self::$ready = true;
        }
    }

    /**
     * 前置
     * 返回为空则不影响路由执行，返回非空将作为请求结果直接返回给客户端
     */
    function before($params)
    {
        foreach (self::$avaiableWare as $name) {
            $obj = Factory::getInstance('\\Woocan\\Midware\\'. $name);
            if ($result = $obj->start($params)) {
                return $result;
            }
        }
        return null;
    }

    /* 后置 */
    function after($response)
    {
        foreach (self::$avaiableWare as $name) {
            $obj = Factory::getInstance('\\Woocan\\Midware\\'. $name);
            $obj->end($response);
        }
    }
}