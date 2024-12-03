<?php
/**
 * Created by PhpStorm.
 * User: LHT
 * Date: 2020/5/15
 * Time: 15:32
 */

namespace Woocan\Connector;

use \Woocan\Core\Interfaces\Connector as IConnector;

abstract class Base implements IConnector
{
    //最后一次激活的时间
    protected $lastActiveTime;
    //连接名字，用于区分连接池中的多个连接
    protected $name;


    public function setLastActiveTime($timestamp)
    {
        $this->lastActiveTime = $timestamp;
    }

    public function getLastActiveTime()
    {
        return $this->lastActiveTime;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getName()
    {
        return $this->name;
    }

    abstract public function disconnect();
}