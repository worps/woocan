<?php
/**
 * Created by PhpStorm.
 * User: ASUS
 * Date: 2019/12/13
 * Time: 22:58
 */

namespace Woocan\Midware;

use \Woocan\Core\Context;
use \Woocan\Core\Request;
use \Woocan\Core\Interfaces\Midware as IMidware;
/**
 * Class ApiStats
 * @package \Woocan\Lib
 * 统计各API调用次数、耗时
 */
class ApiStats implements IMidware
{
    const Table_Length = 1024;
    private $table;

    public function initial($config)
    {
        $this->table = \Woocan\Core\Table::create('ApiStatsTable', self::Table_Length, [
            ['count', \swoole_table::TYPE_INT, 4],
            ['cost_time', \swoole_table::TYPE_INT, 4],
            ['max_time', \swoole_table::TYPE_INT, 1],
            ['min_time', \swoole_table::TYPE_INT, 1],
            ['pdo_count', \swoole_table::TYPE_INT, 4],
            ['pdo_cost_time', \swoole_table::TYPE_INT, 4],
            ['mongo_count', \swoole_table::TYPE_INT, 4],
            ['mongo_cost_time', \swoole_table::TYPE_INT, 4],
            ['redis_count', \swoole_table::TYPE_INT, 4],
        ]);
    }

    function start($queryData)
    {
        Context::set('api_stats_start', microtime(true)*1000);
        Context::set('api_stats_mongo_count', 0);
        Context::set('api_stats_mongo_cost', 0);
        Context::set('api_stats_redis_cost', 0);
    }

    function end($response)
    {
        $class = Request::getCtrl();
        $method = Request::getMethod();
        $startTime = Context::get('api_stats_start');

        if ($startTime && $class && $method){
            $apiName = $class. '::'. $method;
            $costTime = intval(microtime(true)*1000 - $startTime);
            $mongoCount = Context::get('api_stats_mongo_count');
            $mongoCostTime = Context::get('api_stats_mongo_cost');
            $redisCount = Context::get('api_stats_redis_count');
            $pdo = $this->_fetchPdoStats();

            $row = $this->table->get($apiName);
            if ($row){
                $data = [
                    'count' => $row['count'] + 1,
                    'cost_time' => $row['cost_time'] + $costTime,
                    'max_time' => max([$row['max_time'], $costTime]),
                    'min_time' => min([$row['min_time'], $costTime]),
                    'pdo_count'=> $row['pdo_count'] + $pdo['times'],
                    'pdo_cost_time'=> $row['pdo_cost_time'] + $pdo['cost'],
                    'mongo_count'=> $row['mongo_count'] + $mongoCount,
                    'mongo_cost_time'=> $row['mongo_cost_time'] + $mongoCostTime,
                    'redis_count'=> $row['redis_count'] + $redisCount,
                ];
            } else {
                $data = [
                    'count' => 1,
                    'cost_time' => $costTime,
                    'max_time' => $costTime,
                    'min_time' => $costTime,
                    'pdo_count'=> $pdo['times'],
                    'pdo_cost_time'=> $pdo['cost'],
                    'mongo_count'=> $mongoCount,
                    'mongo_cost_time'=> $mongoCostTime,
                    'redis_count'=> $redisCount,
                ];
            }
            $this->table->set($apiName, $data);
        }
    }

    //统计pdo耗时
    private function _fetchPdoStats()
    {
        $ret = [
            'cost' => 0,
            'times' => 0,
        ];
        $pdoStats = Context::get('api_pdo_stats') ?? [];
        foreach ($pdoStats as $info) {
            $ret['times'] += 1;
            $ret['cost'] += $info['cost'];
        }
        return $ret;
    }

    function showAll()
    {
        $ret = [];
        foreach ($this->table as $k => $item){
            $item['_id'] = $k;
            $item['avg_time'] = floor($item['cost_time'] / $item['count']);
            $item['per_pdo_count'] = round($item['pdo_count'] / $item['count'], 1);
            $item['per_pdo_time'] = $item['pdo_count'] > 0 ? floor($item['pdo_cost_time'] / $item['count']) : 0;
            $item['per_mongo_count'] = round($item['mongo_count'] / $item['count'], 1);
            $item['per_mongo_time'] = $item['mongo_count'] > 0 ? floor($item['mongo_cost_time'] / $item['count']) : 0;
            $item['per_redis_count'] = round($item['redis_count'] / $item['count'], 1);
            $ret[$k] = $item;
        }
        return $ret;
    }

    //swoole_table的运行状态
    function tableStats()
    {
        return $this->table ? $this->table->stats() : null;
    }
}
