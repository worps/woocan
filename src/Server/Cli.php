<?php
/**
 * @author lht
 * 命令行模式
 */
namespace Woocan\Server;

use Woocan\Core\Interfaces\Server as IServer;
use Woocan\Core\Factory;

class Cli implements IServer
{
    public function run()
    {
        try{
            if (!isset($_SERVER['argv'][1])){
                exit("no params !");
            }

            $routerParam = parseQuery($_SERVER['argv'][1]);
            if (isset($routerParam['query'])) {
                // 写入请求参数
                $_REQUEST = $_GET = $routerParam['query'];
            }
            echo Factory::getInstance('\\Woocan\\Router\\Api')->dispatch($routerParam);
        } catch (\Throwable $e) {
            \Woocan\Boot::exceptionHandler($e);
        }
    }

    public function getServ()
    {
        return null;
    }
    
    public function isEnableCo()
    {
        return false;
    }
}
