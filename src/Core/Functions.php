<?php

use Woocan\Core\Log;

/**
 * author: lht
 * Date: 13-6-17
 * 函数库
 */
function dirMake($dir, $mode = 0755)
{
    if (\is_dir($dir) || \mkdir($dir, $mode, true)) {
        return true;
    }
    if (!dirMake(\dirname($dir), $mode)) {
        return false;
    }
    return \mkdir($dir, $mode);
}

function dirTree($dir, $filter = '', &$result = array(), $deep = false)
{
        $files = new \DirectoryIterator($dir);
        foreach ($files as $file) {
            if ($file->isDot()) {
                continue;
            }
            $filename = $file->getFilename();
            if ($file->isDir()) {
                if ($deep) {
                    dirTree($dir . DS . $filename, $filter, $result, $deep);
                }
            } else {
                if (!empty($filter) && !\preg_match($filter, $filename)) {
                    continue;
                }
                if ($deep) {
                    $result[$dir][] = $filename;
                } else {
                    $result[] = $dir . DS . $filename;
                }
            }
        }
        return $result;
}

/* 获取类名（去掉namespace部分） */
function shortName($classFullName)
{
    return basename(str_replace('\\', '/', $classFullName));
}

function getServerLanIp()
{
    if (function_exists('swoole_get_local_ip')) {
        $ethArr = swoole_get_local_ip();
        foreach ($ethArr as $netname => $ip) {
            if (preg_match('#^(192|10|172)\.#', $ip)) {
                return $ip;
            }
        }
    } else {
        $ip = gethostbyname(gethostname());
        if (preg_match('#^\d+\.\d+\.\d+\.\d+$#', $ip)) {
            return $ip;
        }
    }
    return null;
}

/**
 * 作用：解析字符串形式的url
 * 返回：如 ['path'=>'info', 'query'=>['a'=>1, 'b'=>2]]
 */
function parseQuery($strQuery)
{
    $result = ['path'=>null, 'query'=>[]];
    if (strrpos($strQuery,'?')===false && strrpos($strQuery,'=')) {
        $strQuery = '?'. $strQuery;
    }
    $query = parse_url($strQuery);
    if (isset($query['query'])) {
        parse_str($query['query'], $result['query']);
    }
    if (isset($query['path'])) {
        $result['path'] = $query['path'];
    }
    return $result;
}

function dump($mix)
{
    if (IS_WIN) {
        if (C('server_mode') != 'Cli') {
            header('Content-Type: text/html; charset=utf-8');
        }
    } else {
        if (is_numeric($mix) || is_string($mix)) {
            $mix = $mix. "\n";
        }
    }

    $caller = backTrace(2);
    echo $caller[0]. " : ";
    var_dump($mix);
}

/**
 * @param $command （1停止 2热重载）
 * @return string
 * @throws \Exception
 */
function serverCommand($command)
{
    $pidFile = C('swoole_main.setting.pid_file');
    if (file_exists($pidFile)) {
        $pid = file_get_contents($pidFile);
        if (!\Swoole\Process::kill($pid, 0)) {
            echo "pid[{$pid}] not exist \n";
            return false;
        }

        switch ($command) {
            case 1:
                \Swoole\Process::kill($pid, SIGTERM);
                while(file_exists($pidFile) && \Swoole\Process::kill($pid, 0)) {
                    usleep(500000);
                }
                echo "server stop done.\n";
                return true;
            case 2:
                \Swoole\Process::kill($pid, SIGUSR1);
                echo "server reload done.\n";
                return true;
            default:
                echo "server command not defined:" . $command;
        }
    } else {
        echo "PID file does not exist!\n";
    }
    return false;
}

/**
* @param $func
* @throws Exception
* 创建协程
*/
function G($func)
{
    Log::debug('debug', 'user call G, ', ...(backTrace()));
    \Woocan\Core\Context::createCo($func);
}

/**
 * RPC调用
 */
function R($rpcAddr, $class, $timeout=2)
{
    return new \Woocan\Rpc\RpcUdpCaller(['addr'=>$rpcAddr], $class, $timeout);
}

/**
 * RPC tcp调用
 */
function Rt($rpcAddr, $class, $timeout=2)
{
    return new \Woocan\Rpc\RpcTcpCaller(['addr'=>$rpcAddr], $class, $timeout);
}



/**
 * 读取配置
 */
function C($key, $default=null)
{
    $fields = explode('.', $key);
    $config = \Woocan\Core\Config::all();

    while($field = array_shift($fields)) {
        if (!isset($config[$field])) {
            return $default;
        }
        $config = $config[$field];
    }
    return $config;
}

/**
 * 记录调用方的链路信息
 */
function backTrace($deep=2)
{
    $result = [];
    $traces = debug_backtrace(2, $deep+1);
    $index = 1;
    while ($line = $traces[$index ++] ?? null) {
        if (isset($line['file'])) {
            $result[] = sprintf("%s(%d)->%s", $line['file'], $line['line'], $line['function']);
        } else {
            $result[] = sprintf("%s::%s", $line['class'], $line['function']);
        }
        
    }
    return $result;
}