<?php
namespace Woocan\Server\Callback;

use Throwable;
use \Woocan\Core\Config;
use \Woocan\Core\Factory;
use \Woocan\Core\Log;
use \Woocan\Core\Pool;
use \Woocan\Core\Context;
use \Woocan\Core\Response;

abstract class Base
{
    protected $isRpc;

    function __construct($config)
    {
        $this->isRpc = $config['is_rpc'] ?? false;
    }

    public function onStart()
    {
        \swoole_set_process_name(APP_NAME .'-master ');
    }

    public function onShutDown()
    {
        $callback = C('project.shutdown_callback');
        if ($callback) {
            call_user_func($callback);
        }
    }

    /**
     * @param $server
     * @throws \Exception
     * @desc 服务启动，设置进程名
     */
    public function onManagerStart($server)
    {
        swoole_set_process_name(APP_NAME . '-manager '.C('server_mode'));
        echo "server started.. \n";
        echo "php version: ". phpversion(). "\n";
        echo "swoole version: ". phpversion('swoole'). "\n";
        echo "-----------------\n";
        system('ps aux|grep -v grep|grep " '.APP_NAME.'-"');
        echo "-----------------\n";
    }

    public function onWorkerStart($server, $workerId)
    {
        try{
            $workNum = C('swoole_main.setting.worker_num');
            if ($workerId >= $workNum) {
                swoole_set_process_name(APP_NAME . "-tasker: " . ($server->worker_id - $workNum) . " pid " . $server->worker_pid);
            } else {
                swoole_set_process_name(APP_NAME . "-worker: {$server->worker_id} pid " . $server->worker_pid);
            }

            //重载配置
            Config::load();
            //防止开启opcache开启情况下，reload时常量不重新加载
            if (function_exists('opcache_reset')) opcache_reset();
            //防止开启apc开启情况下，reload时常量不重新加载
            if (function_exists('apc_clear_cache')) apc_clear_cache();
            //播种随机种子
            mt_srand();
            //连接池
            foreach (C('pool') as $name => $config) {
                Pool::factory($name)->initial();
            }
        } catch (\Throwable $e) {
            echo $e. "\n";
            echo "请修复后重新启动\n";
        }
    }

    public function onTask($server, $taskId, $fromId, $data)
    {
    	Log::debug('debug', "onTask");
        // Route::dispatch($data);
    }

    protected function _handleReq($dataStr, $fd)
    {
        try {
            Log::debug('debug', sprintf("sw received:%s, isRpc=%d", $dataStr, $this->isRpc));

            Context::baseCoSet('_is_rpc', $this->isRpc);

            if ($this->isRpc) {
                $dataStr = trim($dataStr, "\n");
                $routerParam = parseQuery($dataStr);
                $viewStr = Factory::getInstance('\\Woocan\\Router\\Rpc')->dispatch($routerParam);
            } else {
                //消息解码、拆包
                if ($decodeMethod = C('project.msg_decode')) {
                    $dataStr = $decodeMethod($dataStr, $fd);
                }

                //针对拆得的多个包分别处理
                $routerParam = parseQuery($dataStr);
                $viewStr = Factory::getInstance('\\Woocan\\Router\\Api')->dispatch($routerParam);
            }
            Response::output($viewStr);
        } catch (Throwable $e) {
            \Woocan\Boot::exceptionHandler($e);
        }
    }
}
