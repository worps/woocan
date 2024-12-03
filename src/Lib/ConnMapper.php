<?php
/**
 * 长连接映射
 * 
 * 对照长连接fd、user、频道的映射管理器
 */
namespace Woocan\Lib;

class ConnMapper
{
    //如果 $size 不是为 2 的 N 次方，如 1024、8192、65536 等，底层会自动调整为接近的一个数字，如果小于 1024 则默认成 1024
    private static $uidFdTable;
    private static $fdUinfoTable;

    /* swoole前初始化table */
    public static function initial()
    {
        if (IS_SWOOLE && \Woocan\Boot::isSwReady()) {
            echo "swoole_table should be created before swoole starting. \n";
        }
        else if ($config = C('conn_mapper'))
        {
            $tableLength = $config['max_size'] ?? 8192;

            //uid=>fd对应关系
            self::$uidFdTable = \Woocan\Core\Table::create('ConnUidFdTable', $tableLength, [
                ['fd', \swoole_table::TYPE_INT, 4]
            ]);

            //fd=>userinfo对应关系
            $fdUinfoTableCols = [
                ['uid', \swoole_table::TYPE_INT, 4],
                ['info', \swoole_table::TYPE_STRING, 512],
            ];

            //频道
            if (isset($config['channels'])) {
                foreach ($config['channels'] as $channelName) {
                    $fdUinfoTableCols[] = [$channelName, \swoole_table::TYPE_INT, 4];
                }
            }

            self::$fdUinfoTable = \Woocan\Core\Table::create('ConnFdUinfoTable', $tableLength, $fdUinfoTableCols);
        }
    }

    public static function add($uid, $fd, $info)
    {
        //uid=>fd对应关系
        self::$uidFdTable->set($uid, ['fd' => $fd]);
        //fd=>userinfo对应关系
        self::$fdUinfoTable->set($fd, [
            'uid'  => $uid,
            'info' => json_encode($info),
        ]);
    }

    public static function getInfoByFd($fd)
    {
        $value = self::$fdUinfoTable->get($fd, 'info');
        return json_decode($value, true);
    }

    public static function getUidByFd($fd)
    {
        $value = self::$fdUinfoTable->get($fd, 'uid');
        return $value;
    }

    public static function updateInfoByFd($fd, array $update)
    {
        $value = self::$fdUinfoTable->get($fd, 'info');
        $value = json_decode($value, true);
        if ($value) {
            $newVal = array_merge($value, $update);
            self::$fdUinfoTable->set($fd, ['info' => json_encode($newVal)]);
        }
    }

    public static function getFdByUid($uid)
    {
        if (!IS_SWOOLE) {
            return null;
        }
        return self::$uidFdTable->get($uid, 'fd');
    }

    /**
     * @return array
     * DEMO ： Array
    (
        [1]（fd） => Array
        (
            [data] => Array
            (
                [account_id] => 5583
                [login_time] => 1605091307
                [role_name] => test23679481
                [server_id] => 1
                [channel] => 1
                [indulge_lv] => 0
                [ip] => 192.168.50.18
                [last_online_time] => 350
            )

            [world] => 0
            [union] => 0
        )
    )
     * 获取全部在线连接
     */
    public static function getAll()
    {
        $ret = [];
        foreach (self::$fdUinfoTable as $fd => $row)
        {
            $ret[$fd] = [
                'uid'  => $row['uid'],
                'info' => json_decode($row['info'], true),
            ];
        }
        return $ret;
    }

    /* 将已有fd加入某频道 */
    public static function setChannels($fd, $channelsKV)
    {
        if (self::$fdUinfoTable->exist($fd)) {
            self::$fdUinfoTable->set($fd, $channelsKV);
        }
    }

    /**
     * 返回[fd=>uid, ...]
     */
    public static function getChannelUsers($channel_name, $channel_id)
    {
        $ret = [];
        foreach (self::$fdUinfoTable as $fd => $row)
        {
            if ($row[$channel_name] == $channel_id) {
                $ret[$fd] = $row['uid'];
            }
        }
        return $ret;
    }

    public static function delete($uid, $fd)
    {
        self::$uidFdTable->del($uid);
        self::$fdUinfoTable->del($fd);
    }
}