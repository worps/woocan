<?php
/**
 * @author lht
 */
namespace Woocan\Queue;

use Woocan\Core\Pool;
use Woocan\Core\Interfaces\Queue as IQueue;

class RabbitMQ implements IQueue
{
    private $config;

    public function __construct($config)
    {
        if (!isset($config['pool_key']) ){
            throw new \Woocan\Core\MyException('FRAME_CONFIG_LESS', 'pool_key');
        }
        if (!isset($config['exchange']) ){
            throw new \Woocan\Core\MyException('FRAME_CONFIG_LESS', 'exchange');
        }
        $this->config = $config;
    }

    private function _amqp()
    {
        $connectionPool = Pool::factory( $this->config['pool_key'] );
        return $connectionPool->pop();
    }

    /**
     * 写入消息
     * Fanout模式交换机$router_key=null
     */
    public function add($router_key, $data)
    {
        $exchange = $this->_amqp()->createExchange($this->config['exchange']);
        return $exchange->publish($data, $router_key);
    }

    /**
     * get方法性能略差
     */
    public function get($queueName, $ack=true)
    {
        $queue = $this->_amqp()->createQueue($queueName);
        return $queue->get();
    }

    public function getBatch($queueName, $size, $ack=true)
    {
        $list = [];
        $queue = $this->_amqp()->createQueue($queueName);

        $queue->consume(function ($envelope, $queue) use (&$list, &$size, $ack){
            $list[] = $envelope->getBody();
            $ack && $queue->ack($envelope->getDeliveryTag());
            return --$size > 0;
        });
        return $list;
    }

    public function ack($queueName, $msgid)
    {
        $queue = $this->_amqp()->createQueue($queueName);
        $queue->ack($msgid);
    }

    /**
     * 获取消息队列的长度
     */
    public function getLen($queueName)
    {

    }
}