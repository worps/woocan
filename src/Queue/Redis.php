<?php
/**
 * @author lht
 */
namespace Woocan\Queue;

use Woocan\Core\Pool;
use Woocan\Core\Interfaces\Queue as IQueue;

class Redis implements IQueue
{
    private $config;

    public function __construct($config)
    {
        if (!isset($config['pool_key']) ){
            throw new \Woocan\Core\MyException('FRAME_CONFIG_LESS', 'pool_key');
        }
        $this->config = $config;
    }

    private function _redis()
    {
        $connectionPool = Pool::factory( $this->config['pool_key'] );
        return $connectionPool->pop();
    }

    public function add($key, $data)
    {
        return $this->_redis()->rPush($key, $data);
    }

    public function get($key, $ack=true)
    {
        if (!$ack) {
            $list = $this->_redis()->lrange($key, 0, 0);
            if ($list) {
                return array_pop($list);
            }
        } else {
            return $this->_redis()->lPop($key);
        }
    }

    public function getBatch($key, $size, $ack=true)
    {
        $redis = $this->_redis();
        $list = $redis->lrange($key, 0, $size-1);
        if (!empty($list) && $ack) {
            $this->ack($key, count($list)); //不能用$size代替count($list)
        }
        return $list;
    }

    /**
     * 删除前$length条消息
     */
    public function ack($key, $length)
    {
        return $this->_redis()->ltrim($key, $length, -1);
    }

    /**
     * 获取消息队列的长度
     */
    public function getLen($key)
    {
        return $this->_redis()->lLen($key);
    }
}