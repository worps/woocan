<?php
namespace Woocan\Cache;

use \Woocan\Core\Interfaces\Cache as ICache;

/**
 * Class Yac
 * @package Woocan\Cache
 * yac实现PHP进程间数据共享的两个条件是：同族进程，且至少有一个PHP进程在运行中
 * 两个无关的cli执行时不能使用yac共享内存
 */
class Yac implements ICache
{
    private $yac;
    private $expiration = 10800;

    public function __construct($config)
    {
        $this->yac = new \Yac($config['prefix']);

        if (isset($config['cache_expire'])) {
            $this->expiration = $config['cache_expire'];
        }
    }
    
    public function set($key, $value, $expiration = null)
    {
        if ($expiration === null) {
            $expiration = $this->expiration;
        }
        return $this->yac->set($key, $value, $expiration);
    }

    public function get($key)
    {
        return $this->yac->get($key);
    }

    public function getCache($key)
    {
        return $this->get($key);
    }

    public function delete($key)
    {
        return $this->yac->delete($key);
    }

    public function increment($key, $offset = 1)
    {
        $value = $this->get($key) ?? 0;
        $this->yac->set($key, $value + $offset);
        return $this->get($key);
    }

    public function decrement($key, $offset = 1)
    {
        $value = $this->get($key) ?? 0;
        $this->yac->set($key, $value - $offset);
        return $this->get($key);
    }

    public function clear()
    {
        return $this->yac->flush();
    }
}