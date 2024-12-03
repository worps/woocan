<?php
/**
 * @author lht
 * swoole_udp模式
 */
namespace Woocan\Server;

use Woocan\Core\Interfaces\Server as IServer;

class SwooleUdp extends SwooleBase implements IServer
{
    public function __construct()
    {
        parent::__construct();

        $this->serv = new \Swoole\Server(C('swoole_main.host'), C('swoole_main.port'), $this->workMode, SWOOLE_SOCK_UDP);

        printf("swoole_udp listening %s:%d\n", C('swoole_main.host'), C('swoole_main.port'));
    }
}