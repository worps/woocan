<?php
namespace Woocan\Midware;

use \Woocan\Core\Context;
use \Woocan\Core\Request;
use \Woocan\Core\Interfaces\Midware as IMidware;
/**
 * Class PdoStats
 * @package \Woocan\Lib
 * 统计各pdo调用次数、耗时
 */
class PdoStats implements IMidware
{
    const Table_Length = 4096;
    private $table;

    public function initial($config)
    {
        $this->table = \Woocan\Core\Table::create('PdoStatsTable', self::Table_Length, [
            ['count', \swoole_table::TYPE_INT, 4],
            ['cost_time', \swoole_table::TYPE_INT, 4],
            ['max_time', \swoole_table::TYPE_INT, 1],
            ['min_time', \swoole_table::TYPE_INT, 1],
        ]);
    }

    function start($params)
    {}

    function end($response)
    {
        $pdoStats = Context::get('api_pdo_stats') ?? [];
        foreach ($pdoStats as $info) {
            $costTime = $info['cost'];
            $row = $this->table->get($info['caller']);
            if ($row){
                $data = [
                    'count' => $row['count'] + 1,
                    'cost_time' => $row['cost_time'] + $costTime,
                    'max_time' => max([$row['max_time'], $costTime]),
                    'min_time' => min([$row['min_time'], $costTime]),
                ];
            } else {
                $data = [
                    'count' => 1,
                    'cost_time' => $costTime,
                    'max_time' => $costTime,
                    'min_time' => $costTime,
                ];
            }
            $this->table->set($info['caller'], $data);
        }
    }

    function showAll()
    {
        $ret = [];
        foreach ($this->table as $k => $item){
            $item['_id'] = $k;
            $item['avg_time'] = floor($item['cost_time'] / $item['count']);
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
