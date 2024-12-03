<?php
/**
 * author: lht
 * 初始化框架相关信息
 */

namespace Woocan;

use \Woocan\Core\Response;
use \Woocan\Core\Config;
use \Woocan\Core\Log;
use \Woocan\Core\MyException;

class Boot
{
    public static $serv = null;
    
    /**
     * 处理warning、notice等未被catch到的错误
     * 只有fatal和recoverable级别的错误可在try-catch中捕获
     */
    public static function errorHandler($level, $message, $file, $line)
    {
        $info = [
            'level'=>$level,
            'msg'=>$message,
            'file'=>$file,
            'line'=>$line,
        ];
        //记录日志
    	Log::error('cannot throw Error:', $info);
        //客户端展示错误
        Response::error2display('error', $info);
    }
    
    /* 
     * 默认异常处理
     */
    public static function exceptionHandler($e)
    {
        if ($e->getCode() !== MyException::E_Code['NORMAL_EXIT']) {
            //记录异常
            $exceptionArr = Core\Log::exception('exception', $e);
            //客户端展示异常
            Response::error2display('exception', $exceptionArr);
        }
    }

    /** worker退出错误
    public static function fatalHandler()
    {
    	if (is_array($error = \error_get_last())) {
            $info = [
                'code'  => Core\MyException::E_Code['FRAME_SYSTEM_ERR'],
                'msg'   => $error['message'],
                'file'  => $error['file'],
                'line'  => $error['line'],
            ];
            //日志记录
            Core\Log::error('fatal', $info);
            Response::error2display('exception', $info);
        }
    } */
    
    /**
     * 预设框架运行参数
     */
    public static function init()
    {
        self::setConstant(false);

        require WOOCAN_PATH. "/Core/Functions.php";
    	Config::load();

    	//vendor
        if (C('project.use_vendor')) require ROOT_PATH. "/vendor/autoload.php";

        //预加载文件
        if ($preloadFiles = C('project.preload_files')) {
            foreach ($preloadFiles as $prefile) require $prefile;
        }

    	set_error_handler('\\Woocan\\Boot::errorHandler', E_ALL);
    	set_exception_handler('\\Woocan\\Boot::exceptionHandler');
        //register_shutdown_function('\\Woocan\\Boot::fatalHandler');

    	//socket流（mysql、redis）读取/写入超时时间
    	ini_set('default_socket_timeout', C('project.socket_stream_timeout'));
    	//打开错误输出，显示未被errorHandler捕获的错误
    	ini_set('display_errors', 0);
    	//直接输出错误，防止某些情况下致命错误不能输出
    	ini_set('error_log', C('project.log_path').'/fatalCenter.log');
    	//错误级别
        error_reporting(E_ALL ^ E_NOTICE);
        date_default_timezone_set(C('project.time_zone'));

    	self::setConstant(true);

        //初始化swoole_table
        \Woocan\Lib\ConnMapper::initial();
        //初始化session
        \Woocan\Lib\Session::init();
        //初始化中间件
        \Woocan\Core\Midware::initial();
    }

    public static function run()
    {
        $serverMode = C('server_mode');
        $serverObj = Core\Factory::getInstance('\\Woocan\\Server\\'. $serverMode);
        self::$serv = $serverObj->getServ();

        $serverObj->run();
    }

    public static function setConstant($initOK)
    {
        if (!$initOK) { //初始化前
            defined('ROOT_PATH') or exit('const ROOT_PATH undefined!');
            defined('APP_NAME') or exit('const APP_NAME undefined!');
            defined('MODULE_NAME') or exit('const MODULE_NAME undefined!');
            defined('APP_FULL_NAME') or define('APP_FULL_NAME', 'app_'.APP_NAME);
            defined('DS') or define('DS', DIRECTORY_SEPARATOR);
            defined('WOOCAN_PATH') or define('WOOCAN_PATH', __DIR__);
            define('IS_WIN', strtoupper(substr(PHP_OS,0,3)) === 'WIN');
        } else { //初始化后
            $serverMode = C('server_mode');
            $isSwoole = in_array($serverMode, ['SwooleHttp','SwooleWs','SwooleTcp','SwooleUdp']);
            $isCo = $isSwoole && C('swoole_main.setting.enable_coroutine', false);

            define('IS_SWOOLE', $isSwoole);
            define('IS_ENABLE_CO', $isCo);
        }
    }

    /**
     * swoole服务是否已经启动
    */
    public static function isSwReady()
    {
        return self::$serv !== null;
    }

    /**
     * 框架入口
     */
    public static function entrance()
    {
    	self::init();

        $cmd = $_SERVER['argv'][1] ?? 'start';

        switch ($cmd) {
            case 'start':
                self::run();
                break;
            case 'stop':
                serverCommand(1);
                break;
            case 'reload':
                serverCommand(2);
                break;
            case 'restart':
                //进入后台执行
                \Swoole\Process::daemon();
                $p = new \Swoole\Process(function() {
                    echo "\n";
                    serverCommand(1) && self::run();
                });
                $p->start();
                break;
            default:
                printf("command(%s) not defined!\n", $cmd);
                break;
        }
    }
}
