<?php
/**
 * Created by PhpStorm.
 * User: LHT
 * Date: 2020/6/3
 * Time: 10:40
 */

namespace Woocan\Core\Interfaces;


interface Midware
{
    /**
     * worker启动前初始化
     */
    public function initial($config);

    /**
     * 接口处理前
     */
    public function start($params);

    /**
     * 接口完成之后
     */
    public function end($response);
}