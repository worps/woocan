<?php
namespace Woocan\Server\Cluster;

use \Woocan\Core\Pool;
use \Woocan\Core\Context;
use \Woocan\Core\Interfaces\Cluster as iCluster;

class SwooleWs implements iCluster
{
    private $clientPool = [];

    function __construct()
    {
        if (!C('cluster.pool_key')) {
            throw new \Woocan\Core\MyException('FRAME_CONFIG_LESS', 'cluster.pool_key');
        }

        //创建client连接池
        $this->_resetClientPool();

        //定时检测新的节点，ping包由服务端发送因此这里不需要定时ping
        $this->_timer();
    }

    public function broadcast($msg)
    {
        foreach ($this->clientPool as $nodeId => $cli) {
            $cli->push($msg);
        }
    }

    public function getClusterNodes()
    {
        $list = $this->_redis()->hGetAll(self::_makeKey('ws'));
        foreach ($list as $id => $info) {
            $list[$id] = json_decode($info, true);
        }
        return $list;
    }

    public static function addMe()
    {
        G(function() {
            $data = json_encode([
                'host' => C('swoole_main.host'),
                'port' => C('swoole_main.port'),
            ]);
            $redis = self::_redis();
            return $redis->hSet(self::_makeKey('ws'), C('cluster.id'), $data);
        });
    }

    public static function removeMe()
    {
        G(function() {
            $redis = self::_redis();
            return $redis->hDel(self::_makeKey('ws'), C('cluster.id'));
        });
    }

    private static function _redis()
    {
        $redisPool = Pool::factory(C('cluster.pool_key'));
        return $redisPool->pop();
    }

    private static function _makeKey($key)
    {
        return "cluster_{$key}";
    }

    private function _resetClientPool()
    {
        $nodes = $this->getClusterNodes();

        //关闭无效节点
        foreach ($this->clientPool as $oNodeId => $item) {
            if (!isset($nodes[$oNodeId])) {
                $item->close();
                unset($this->clientPool[$oNodeId]);
            }
        }

        //加入新节点
        foreach ($nodes as $nodeId => $info) {
            if (!isset($this->clientPool[$nodeId])) {
                $cli = new \swoole_http_client($info['host'], $info['port']);
                $cli->setHeaders(['Trace-Id' => md5(time()),]);
                $cli->on('message', function ($cli, $Woocan) {
                    //使用upgrade方法必须设置该回调
                });
                $cli->upgrade('/', function ($cli) {
                    //发起WebSocket握手请求，并将连接升级为WebSocket。
                });

                $this->clientPool[$nodeId] = $cli;
            }
        }
    }

    private function _timer()
    {
        G(function() {
            while(true) {
                //加入新节点的客户端、关闭不存在的节点的客户端
                $this->_resetClientPool();

                Context::sleep(60);
            }
        });
    }
}