<?php
/**
 * Created by PhpStorm.
 * User: LHT
 * Date: 2020/5/15
 * Time: 15:09
 */

namespace Woocan\Core\Interfaces;

/**
 * Class Connector
 * @package Woocan\Core\Interfaces
 *
 * 客户端连接，如mysql、redis、mongodb
 */
interface Connector
{
    /*  最后一次被激活时间，用于清理idle连接 */
    public function setLastActiveTime($timestamp);

    public function getLastActiveTime();

    public function disconnect();
}