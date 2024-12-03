<?php
namespace Woocan\Server;

use Woocan\Core\Factory;
use Woocan\Core\Config;

abstract class SwooleBase
{
    protected $ssl = 0;
    protected $config;
    protected $serv;
    protected $workMode;
    protected $enableCo = false;
    
    public function __construct()
    {
        $mainConfig = C('swoole_main');
        
        if (!\extension_loaded('swoole')) {
            throw new \Woocan\Core\MyException('FRAME_SYSTEM_ERR', "no swoole extension");
        }
        if ( phpversion('swoole') < 4.0){
            throw new \Woocan\Core\MyException('FRAME_SYSTEM_ERR', "need swoole version 4.0+");
        }

        $this->workMode = $mainConfig['setting']['work_mode'] ?? SWOOLE_PROCESS;
        $this->ssl = isset($mainConfig['setting']['ssl_cert_file'], $mainConfig['setting']['ssl_key_file']);

        //启用协程
        \Swoole\Runtime::enableCoroutine(IS_ENABLE_CO);
    }
    
    public function run()
    {
        //设置主服务参数
        $this->serv->set(C('swoole_main.setting'));
        //回调class
        $callbackClass = C('swoole_main.callback_class') ?? '\\Woocan\\Server\\Callback\\'. C('server_mode');
        $callbackObj = Factory::getInstance($callbackClass, ['_key'=>'main', 'is_rpc'=>false]);
        //设置回调
        $this->_setCallbackHandler($this->serv, $callbackObj);
        $this->_setRequestCallbackHandler($this->serv, $callbackObj);

        //RPC服务
        $rpcConfig = C('swoole_rpc');
        if (isset($rpcConfig['host']) && isset($rpcConfig['port'])) {
            $type = SWOOLE_SOCK_UDP;
            $rpcMode = 'SwooleUdp';
            if (isset($rpcConfig['type']) && $rpcConfig['type'] == SWOOLE_SOCK_TCP) {
                $type = SWOOLE_SOCK_TCP;
                $rpcMode = 'SwooleTcp';
            }
            $rpcServer = $this->serv->addListener($rpcConfig['host'], $rpcConfig['port'], $type);
            //服务参数设置
            $rpcServer->set($rpcConfig['setting']);
            //设置回调
            $callbackObj = Factory::getInstance('\\Woocan\\Server\\Callback\\'. $rpcMode, ['_key'=>'rpc', 'is_rpc'=>true]);
            $this->_setRequestCallbackHandler($rpcServer, $callbackObj);

            printf("RPC listening %s:%d\n", $rpcConfig['host'], $rpcConfig['port']);
        }

        $this->serv->start();
    }

    private function _setCallbackHandler($server, $handlerObj)
    {
        if (!$handlerObj){
            throw new \Woocan\Core\MyException('FRAME_SYSTEM_ERR', 'sw callback class not exists');
        }

        //设置回调
        $handlerArray = array(
            'onStart',
            'onShutdown',
            'onConnect',
            'onClose',
            'onTimer',
            'onWorkerStart',
            'onWorkerStop',
            'onWorkerError',
            'onTask',
            'onFinish',
            'onManagerStart',
            'onManagerStop',
            'onPipeMessage',
            'onHandShake',
            'onOpen',
        );
        foreach ($handlerArray as $handler) {
            if (method_exists($handlerObj, $handler)) {
                $method = strtolower(substr($handler, 2));
                $server->on($method, array($handlerObj, $handler));
            }
        }
    }

    /** 用户请求处理 */
    private function _setRequestCallbackHandler($server, $handlerObj)
    {
        if (!$handlerObj){
            throw new \Woocan\Core\MyException('FRAME_SYSTEM_ERR', 'sw callback class not exists');
        }

        //设置回调
        $handlerArray = array(
            'onPacket',
            'onReceive',
            'onRequest',
            'onMessage',
        );
        
        foreach ($handlerArray as $handler) {
            if (method_exists($handlerObj, $handler)) {
                $method = substr($handler, 2);
                $server->on($method, [$handlerObj, $handler]);
            }
        }
    }
    
    public function getServ()
    {
        return $this->serv;
    }
    
    public function isEnableCo()
    {
        return $this->enableCo;
    }
}