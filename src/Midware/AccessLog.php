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
 * 接口请求日志
 */
class AccessLog implements IMidware
{
    const Log_Length = 1024;
    private $file; //日志文件
    private $fileHander; //日志文件操作句柄
    private $config;

    const Keys_Method_Map = [
        'ip' => '\\Woocan\\Core\\Request::getClientIp',
        'ctrl' => '\\Woocan\\Core\\Request::getCtrl',
        'method' => '\\Woocan\\Core\\Request::getMethod',
        'arg' => '\\Woocan\\Core\\Request::getParams',
        'fd' => '\\Woocan\\Midware\\AccessLog::_getFd',
        'uid'=> '\\Woocan\\Midware\\AccessLog::_getUid',
        'time' => '\\Woocan\\Midware\\AccessLog::_getTime',
    ];

    public function initial($config)
    {
        //日志格式
        if (!isset($config['key'])) {
            throw new \Exception('AccessLog need key param');
        }
        //检查要记录的字段
        foreach ($config['key'] as $field) {
            if (!isset(self::Keys_Method_Map[$field])) {
                throw new \Exception('AccessLog invalid key '. $field);
            }
        }

        $this->config = $config;
    }

    function start($queryData)
    {
        $fields = [];
        foreach ($this->config['key'] as $field) {
            $method = self::Keys_Method_Map[$field];
            $value = call_user_func($method, []);
            $fields[] = is_array($value) ? json_encode($value) : $value;
        }

        //按周生成日志
        $logFile = C('project.log_path'). sprintf('/accesslog_%s.log', date('YW'));
        if ($this->file) {
            if ($this->file != $logFile) {
                //关闭原句柄
                @fclose($this->fileHander);
                //打开新文件
                $this->file = $logFile;
                $this->fileHander = @fopen($this->file, 'a');
            }
        } else {
            $this->file = $logFile;
            $this->fileHander = @fopen($this->file, 'a');
        }

        @fwrite($this->fileHander, implode('|', $fields). "\n");
    }

    function end($response) {}

    private static function _getTime()
    {
        $time = Context::baseCoGet('_request_time') ?? time();
        return date('Y-m-d H:i:s', $time);
    }

    private static function _getFd()
    {
        return \Woocan\Core\Context::baseCoGet('_fd');
    }

    private static function _getUid()
    {
        $fd = \Woocan\Core\Context::baseCoGet('_fd');
        if ($fd > 0) {
            $conInfo = \Woocan\Lib\ConnMapper::getUidByFd($fd);
            return $conInfo['_uid'];
        }
        return -1;
    }

    function __destruct()
    {
        if ($this->fileHander) {
            @fclose($this->fileHander);
        }
    }
}
