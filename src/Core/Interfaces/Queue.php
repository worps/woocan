<?php
/**
 * @author lht
 * 消息队列接口
 */
namespace Woocan\Core\Interfaces;

interface Queue
{
    /**
     * @param $key
     * @return mixed
     * @desc 取出队头数据
     */
    public function get($key, $ack=true);

    /**
     * @param $key
     * @param $size
     * @param bool $ack
     * @return mixed
     * 批量拉取消息
     */
    public function getBatch($key, $size, $ack=true);

    /**
     * 应答
     */
    public function ack($key, $job);

    /**
     * @param $key
     * @param $data
     * @return mixed
     * @desc 向队尾里添加数据
     */
    public function add($key, $data);


    /**
     * 获取消息队列的长度
     */
    public function getLen($key);
}