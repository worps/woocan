<?php
/**
 * @author lht
 * 连接池接口
 */
namespace Woocan\Core\Interfaces;

interface Pool
{
    /* 初始化连接池 */
    public static function init();

    /* 获取一个连接实例 */
    public static function pop($name);

    /* 放回一个连接实例 */
    public static function push($name, $instance);

    /* 释放全部实例 */
    public static function release();
}