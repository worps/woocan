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
                self::_getHandler($wareName)->initial($config);

                self::$avaiableWare[] = $wareName;
            }
            self::$ready = true;
        }
    }

    private static function _getHandler($wareName)
    {
        if (strpos($wareName, '\\') === false) {
            $instance = Factory::getInstance('\\Woocan\\Midware\\'. $wareName);
        } else {
            $instance = Factory::getInstance($wareName);
        }
        return $instance;
    }

    /**
     * 前置
     * 返回为空则不影响路由执行，返回非空将作为请求结果直接返回给客户端
     */
    function before($params)
    {
        foreach (self::$avaiableWare as $name) {
            $result = self::_getHandler($name)->start($params);
            if ($result) {
                return $result;
            }
        }
        return null;
    }

    /* 后置 */
    function after($response)
    {
        foreach (self::$avaiableWare as $name) {
            self::_getHandler($name)->end($response);
        }
    }
}