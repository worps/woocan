<?php
/**
 * @author lht
 * php-fpm模式
 */
namespace Woocan\Server;

use \Woocan\Core\Interfaces\Server as IServer;
use \Woocan\Core\Factory;

class Fastcgi implements IServer
{
    public function run()
    {
        try {
            $path = $_SERVER['REQUEST_URI'];
            $pathArr = explode('.php', $path);
            $argPath = explode('?', array_pop($pathArr));

            $routerParam = [
                'query' => $_GET,
                'path' => $argPath[0],
            ];

            $viewStr = Factory::getInstance('\\Woocan\\Router\\Api')->dispatch($routerParam);
            if ($encodeMethod = C('project.msg_encode')) {
                $viewStr = $encodeMethod([$viewStr], null);
            }
            echo $viewStr;
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