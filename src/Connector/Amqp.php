<?php
/**
 * Created by PhpStorm.
 * User: LHT
 * Date: 2020/5/15
 * Time: 17:26
 *
 * 参考
 * https://www.jb51.net/article/158162.htm
 * https://blog.csdn.net/hellozpc/article/details/81436980
 *
 * RabbitMQ windows下扩展安装
 * 1.http://pecl.php.net/package/amqp下载扩展，扩展压缩包中有两个dll文件
 * 2.将php_amqp.dll放ext扩展目录，并在php.ini中包含进来
 * 3.将rabbit.4.dll放到php.exe所在目录
 *
 * RabbitMQ client
 */
namespace Woocan\Connector;

use \Woocan\Core\MyException;

class Amqp extends Base
{
    private $config;
    private $conn;
    private $channel;
    private $exchange;
    private $queue;

    public function __construct($config)
    {
        if (!isset($config['host']) || !isset($config['port']) || !isset($config['user']) || !isset($config['password'])
            || !isset($config['vhost'])
        ) {
            throw new MyException('FRAME_SYSTEM_ERR', "配置不完整!");
        }
        $this->config = $config;
    }

    public function connect()
    {
        try {
            if ($this->conn === NULL) {
                $connConfig = array(
                    'host' => $this->config['host'],
                    'port' => $this->config['port'],
                    'login' => $this->config['user'],
                    'password' => $this->config['password'],
                    'vhost' => $this->config['vhost'],
                );
                $this->conn = new \AMQPConnection($connConfig);
                $this->conn->connect();
            }
        } catch(Exception $e) {
            //
            //
            throw $e;
        }
        return $this->conn;
    }

    public function createChannel()
    {
        if ($this->channel === null) {
            $conn = $this->connect();
            $this->channel = new \AMQPChannel($conn);
        }
        return $this->channel;
    }

    public function createExchange($exchangeName)
    {
        if ($this->exchange === null) {
            $channel = $this->createChannel();

            $this->exchange = new \AMQPExchange($channel);
            $this->exchange->setName($exchangeName);
            /*
             * 需要创建exchange请到服务端控制面板创建
             *
            $this->exchange->setType(AMQP_EX_TYPE_FANOUT);
            $this->exchange->setFlags(AMQP_DURABLE);
            $this->exchange->declareExchange();
            */
        }
        return $this->exchange;
    }

    public function createQueue($queueName)
    {
        if ($this->queue === null) {
            $channel = $this->createChannel();

            $this->queue = new \AMQPQueue($channel);
            $this->queue->setName($queueName);
            /*
             * 需要创建queue请到服务端控制面板创建
             *
            $this->queue->setFlags(AMQP_DURABLE);
            $this->queue->declareQueue();
            $this->queue->bind($this->config['exchange'], $this->config['router_key']);
             */
        }
        return $this->queue;
    }

    public function disconnect()
    {
        $this->exchange = null;
        $this->queue = null;

        if ($this->channel) {
            $this->channel->close();
        }
        if ($this->conn) {
            $this->conn->disconnect();
        }
    }

    function __destruct()
    {
        $this->disconnect();
    }
}