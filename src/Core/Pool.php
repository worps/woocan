<?php
/**
 * Created by PhpStorm.
 * User: LHT
 * Date: 2020/5/15
 * Time: 11:18
 * 连接池
 *
 * 注意：
 * 连接池并不负责维护每个连接的心跳
 */
namespace Woocan\Core;


class Pool
{
    protected $chan;

    /**
     * 存在的总连接数（含被征用）
     */
    protected $totalSize = 0;

    /**
     * 峰值数量（用于给每个连接设唯一id）
     */
    protected $maxId = 0;

    /**
     * 最小连接数
     */
    protected $minSize = 2;

    /**
     * 最大连接数
     */
    protected $maxSize = 30;

    /**
     * 连接池是否已经就绪
     */
    protected $isReady = false;

    /**
     * 连接的最大闲置时间，超过将被清理
     */
    protected $idleMaxTime = 600;

    /**
     * 清理周期，秒
     */
    protected $idleCheckInterval = 60;

    protected $idleCheckTimer;

    protected $connector;

    public $poolName = '';

    /**
     * 用户传入的配置
     */
    protected $config;

    public static function factory($poolName)
    {
        $config = C('pool.'. $poolName, null);
        if (!$config) {
            throw new MyException('FRAME_CONFIG_LESS', 'pool.'.$poolName);
        }
        $instance = Factory::getInstance(static::class, $config);
        $instance->poolName = $poolName;
        return $instance;
    }

    function __construct($config)
    {
        if (!isset($config['connector'])) {
            throw new MyException('FRAME_SYSTEM_ERR', 'connector is necessary');
        }
        $this->config = $config;
        $this->connector = $config['connector'];

        $this->setAvailableProperty('minSize', 'min_size');
        $this->setAvailableProperty('maxSize', 'max_size');
        $this->setAvailableProperty('idleMaxTime', 'idle_max_time');
        $this->setAvailableProperty('idleCheckInterval', 'idle_check_interval');

        if ($this->maxSize < $this->minSize) {
            $this->maxSize = $this->minSize;
        }
        IS_ENABLE_CO ?
            $this->chan = new \chan($this->maxSize) :
            $this->chan = [];
    }

    protected function setAvailableProperty($property, $key)
    {
        if (isset($this->config[$key]) && $this->config[$key] > 0) {
            $this->$property = $this->config[$key];
        }
    }

    function initial()
    {
        if (!$this->isReady && IS_ENABLE_CO) {
            /*
            for ($i=0; $i<$this->minSize; $i++)
            {
                $conn = $this->createConn();
                $this->push( $conn );
            }
            */
            $this->isReady = true;
            $this->idleCheck();
        }
    }

    /* 当前存在的总连接数 */
    function getTotalSize()
    {
        return $this->totalSize;
    }

    /* 当前库存连接数 */
    function getRetainSize()
    {
        if ($this->chan instanceof \Swoole\Coroutine\Channel) {
            return $this->chan->length();
        }
        return 0;
    }

    /* 池容量 */
    function getCapacity()
    {
        return $this->maxSize;
    }

    /* 累积创建的连接数 */
    function getTotalUsedNum()
    {
        return $this->maxId;
    }

    /* 从连接池获取可用连接 */
    function pop()
    {
        if (IS_ENABLE_CO) {
            if (!$this->isReady) {
                throw new MyException('FRAME_ON_STARTING', 'pop from null of connPool');
            }

            //该协程已经pop过
            $k = '_pool_' . $this->poolName;
            $conn = Context::get($k);
            if (!$conn) {
                //检查库存
                if ($this->getRetainSize() <= 0) {
                    if ($this->getTotalSize() >= $this->maxSize) {
                        throw new MyException('FRAME_SYSTEM_ERR', 'reach maxSize of connPool');
                    }
                    $conn = $this->createConn();
                } else {
                    $conn = $this->chan->pop();
                }
                if (!$conn) {
                    throw new MyException('FRAME_SYSTEM_ERR', 'pop fail from connPool');
                }
                //更新激活时间
                $conn->setLastActiveTime(time());
                //保存协程中
                Context::set($k, $conn);
                //协程结束时归还
                defer(function() use ($conn) {
                    $this->push($conn);
                });
                //打印
                $tips = sprintf("co#%d pop %s\n", Context::getCid(), $conn->getName());
                Log::debug('pool', $tips);
            }
            return $conn;
        } else {
            if (!isset($this->chan[0])) {
                $this->chan[0] = $this->createConn();
            }
            return $this->chan[0];
        }
    }

    /* 将连接放入连接池 */
    function push($conn)
    {
        if (IS_ENABLE_CO) {
            $this->chan->push($conn);

            $tips = sprintf("co#%d push %s\n", Context::getCid(), $conn->getName());
            Log::debug('pool', $tips);
        }
    }

    /**
     * 健康检查
     */
    protected function idleCheck()
    {
        if (IS_ENABLE_CO) {
            $this->idleCheckTimer = \Swoole\Timer::tick($this->idleCheckInterval * 1000, function () {
                /*if ($this->getTotalSize() <= $this->minSize) {
                    return;
                }*/
                if ($this->getRetainSize() <= 0) {
                    return;
                }

                $time = time();
                $remainSize = $this->getRetainSize();

                for ($i=0; $i < $remainSize; $i++) {
                    $conn = $this->chan->pop();
                    if ($conn) {
                        //闲置超时 清理
                        if ($conn->getLastActiveTime() + $this->idleMaxTime < $time) {
                            $conn->disconnect();
                            $this->totalSize --;

                            Log::debug('pool', 'timer discard '. $conn->getName());
                            continue;
                        }
                        $this->push($conn);
                    } else {
                        return;
                    }
                }
            });
        }
    }

    protected function createConn()
    {
        $this->totalSize ++;

        $connector = $this->connector;
        $conn = new $connector($this->config);

        //设置连接名字
        $this->maxId ++;
        $conn->setName($this->poolName. $this->maxId);

        $tips = sprintf("co#%d create %s\n", Context::getCid(), $conn->getName());
        Log::debug('pool', $tips);

        return $conn;
    }

    function __destruct()
    {
        if ($this->idleCheckTimer) {
            \Swoole\Timer::clear($this->idleCheckTimer);
        }
    }
}
