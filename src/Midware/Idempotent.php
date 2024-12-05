<?php
/**
 * Created by PhpStorm.
 * User: ASUS
 * Date: 2019/12/13
 * Time: 23:01
 */

namespace Woocan\Midware;

/**
 * Class Idempotent
 * @package \Woocan\Lib
 * 幂等性控制
 */
use \Woocan\Core\Interfaces\Midware as IMidware;

class Idempotent implements IMidware
{
    const Table_Length = 20480;
    const Data_Field_Length = 10240;
    const Expire_Check_Time = 60;

    private $table;
    private $lastCheckTime;

    public function initial($config)
    {
        $this->table = \Woocan\Core\Table::create('ConnUidFdTable', self::Table_Length, [
            ['data', \swoole_table::TYPE_STRING, self::Data_Field_Length],
            ['create_time', \swoole_table::TYPE_INT, 4]
        ]);
        $this->lastCheckTime = time();
    }

    /* 清理过期数据 */
    private function _clear()
    {
        $time = time();

        if ($this->lastCheckTime + self::Expire_Check_Time < $time) {
            $keys = [];
            foreach ($this->table as $k => $row) {
                if ($row['create_time'] + self::Expire_Check_Time < $time) {
                    $keys[] = $k;
                }
            }
            foreach ($keys as $k) {
                $this->table->del($k);
            }
        }
    }

    function start($queryData)
    {
        if (isset($queryData['_id']) && $this->table->exist($queryData['_id'])) {
            $result = $this->table->get($queryData['_id'], 'data');
            return json_decode($result, true);
        }
        return null;
    }

    function end($response)
    {
        if (strlen($response) < self::Data_Field_Length) {
            $params = \Woocan\Core\Request::getParams();

            if (isset($params['_id']) && is_string($response)) {
                $this->table->set($params['_id'], [
                    'data' => $response,
                    'create_time' => time(),
                ]);
            }
        }
    }
}