<?php
namespace Woocan\Cache;

use Woocan\Core\Interfaces\Cache as ICache;

class Memcache implements ICache
{
    /**
     * Memcached对象
     *
     * @var \Memcached
     */
    private $memcache;

    public function enable()
    {
        return true;
    }

    /**
     * 取得Memcached对象
     *
     * @return \Memcached
     */
    function getMemcached()
    {
        return $this->memcache;
    }

    /**
     * 添加新数据（如存在则失败）
     *
     * @param string $key
     * @param mixed $value
     * @param int $expiration
     * @return bool
     */
    public function add($key, $value, $expiration = 0)
    {
    	$value = json_encode($value);
        return $this->memcache ? $this->memcache->add($key, $value, false, $expiration) : false;
    }

    /**
     * 替换指定键名的数据（如不存在则失败）
     *
     * @param string $key
     * @param mixed $value
     * @param int $expiration
     * @return bool
     */
    public function replace($key, $value, $expiration = 0)
    {
    	$value = json_encode($value);
        return $this->memcache ? $this->memcache->replace($key, $value, false, $expiration) : false;
    }

    /**
     * 存储指定键名的数据（如存在则覆盖）
     *
     * @param string $key
     * @param mixed $value
     * @param int $expiration
     * @return bool
     */
    public function set($key, $value, $expiration = 0)
    {
    	$value = json_encode($value);
        return $this->memcache ? $this->memcache->set($key, $value, false, $expiration) : false;
    }

    /**
     * 存储指定数据序列（如存在则覆盖）
     *
     * @param array $items
     * @param int $expiration
     * @return bool
     */
    public function setMulti($items, $expiration = 0)
    {
        return $this->memcache ? $this->memcache->setMulti($items, $expiration) : false;
    }

    /**
     * 获取指定键名的数据
     *
     * @param string $key
     * @return mixed
     */
    public function get($key)
    {
        $value = $this->memcache ? $this->memcache->get($key) : null;
        return json_decode($value, true);
    }

    /**
     * 获取指定键名序列的数据
     *
     * @param array $keys
     * @return array
     */
    public function getMulti($keys)
    {
        return $this->memcache ? $this->memcache->getMulti($keys) : null;
    }

    /**
     * 增加整数数据的值
     *
     * @param string $key
     * @param int $offset
     * @return bool
     */
    public function increment($key, $offset = 1)
    {
        return $this->memcache ? $this->memcache->increment($key, $offset) : false;
    }

    /**
     * 减少整数数据的值
     *
     * @param string $key
     * @param int $offset
     * @return bool
     */
    public function decrement($key, $offset = 1)
    {
        return $this->memcache ? $this->memcache->decrement($key, $offset) : false;
    }

    /**
     * 删除指定数据
     *
     * @param string $key
     * @return bool
     */
    public function delete($key)
    {
        return $this->memcache ? $this->memcache->delete($key) : false;
    }

    /**
     * 删除指定键名序列的数据
     *
     * @param array $keys
     * @return bool
     */
    public function deleteMulti($keys)
    {
        if (!$this->memcache || empty($keys)) {
            return false;
        }

        foreach ($keys as $key) {
            $this->memcache->delete($key);
        }

        return true;
    }

    /**
     * 无效化所有缓存数据（清空缓存，慎用）
     *
     * @return bool
     */
    public function clear()
    {
        return $this->memcache ? $this->memcache->flush() : false;
    }

    /**
     * 获取服务器统计信息
     *
     * @return array
     */
    public function stat()
    {
        return $this->memcache ? $this->memcache->getStats() : null;
    }
}