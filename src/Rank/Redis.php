<?php

namespace Woocan\Rank;

use Woocan\Core\Pool;
use Woocan\Core\Interfaces\Rank as IRank;

/**
 * 排行榜(只支持降序)
 * 误差1分钟内分数相同的按redis字典排序
 * */
class Redis implements IRank
{
    private $keyPrefix = 'rank';

    private $config;

    public function __construct($config)
    {
        if (!isset($config['pool_key'])) {
            throw new \Woocan\Core\MyException('FRAME_CONFIG_LESS', 'pool_key');
        }
        $this->config = $config;
    }

    public function getRankList($key, $limit = 100)
    {
        $list = $this->getRangeByRank($key, 0, $limit - 1);

        return $list;
    }

    public function replaceIntoRank($key, $score, $id, $info)
    {
        $key = $this->_makeKey($key);
        $score = $this->_makeScore($score, $info);
        $this->_redis()->zAdd($key, $score, $id);
        $this->_setInfoList($key, [$id => json_encode($info)]);

        return true;
    }

    public function getRangeByRank($key, $start, $end)
    {
        $infoKey = $this->_makeKey($key);
        $list = $this->_redis()->zRevRange($infoKey, $start, $end);
        if ($list) {
            $list = $this->_mergeInfoList($infoKey, $list, $start);
        }

        return $list;
    }

    /*public function getRangeByScore($key, $start, $end)
    {
        return $this->_redis()->zRangeByScore($key, $start, $end);
    }*/

    public function getRankById($key, $id)
    {
        $key = $this->_makeKey($key);
        $rank = $this->_redis()->zRevRank($key, $id);

        if (false === $rank) {
            return 0;
        }

        return ++$rank;
    }

    private function _getInfoMemkey($key)
    {
        return $key . '_infolist';
    }

    private function _setInfoList($key, $info)
    {
        $infoKey = $this->_getInfoMemkey($key);
        return $this->_redis()->hMset($infoKey, $info);
    }

    /* 加入时间进行偏移 */
    private function _makeScore($score, $info = [])
    {
        if (isset($info['update_rank_time'])){
            $score = $score * 1000000000 + (1999999999- $info['update_rank_time']);
        }else{
            $score = $score * 1000000000 + (1999999999- time());
        }

        return $score;
    }

    private function _mergeInfoList($infoKey, $list, $start)
    {
        if(!empty($list)){
            $rows = [];
            $infoKey = $this->_getInfoMemkey($infoKey);
            $infoList = $this->_redis()->hGetAll($infoKey);
            foreach($list as $index => $v){
                $rank = $start + $index + 1;
                //$index = $this->getRankById($key, $v);
                $rows[$rank] = json_decode($infoList[$v],true);
            }

            return $rows;
        }

        return $list;
    }

    public function initData($key, $initData, $expire_time)
    {
        if (is_array($initData)) {
            $infoList = [];
            $key = $this->_makeKey($key);
            $data[] = $key;
            foreach($initData as $item){
                $infoList[$item['uid']] = json_encode($item);
                $score = $this->_makeScore($item['score'], $item);
                $data[] = $score;
                $data[] = $item['uid'];
                //$this->_redis()->zAdd($key, $score, $item['uid']);
            }

            call_user_func_array(array($this->_redis(), 'zAdd'), $data);

            $infoKey = $this->_getInfoMemkey($key);
            $this->_redis()->hMset($infoKey, $infoList);

            if ($expire_time > 0) {
                $this->_redis()->expire($infoKey, $expire_time);
                $this->_redis()->expire($key, $expire_time);
            }
        }
    }

    public function flushRank($key)
    {
        $key = $this->_makeKey($key);
        $this->_redis()->del($key);
        $this->_redis()->del($this->_getInfoMemkey($key));
    }

    private function _redis()
    {
        $connectionPool = Pool::factory($this->config['pool_key']);
        return $connectionPool->pop();
    }

    private function _makeKey($key)
    {
        return $key . '_' . $this->keyPrefix;
    }

}