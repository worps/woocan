<?php
namespace Woocan\Core\Interfaces;

interface Cluster
{
    /* 向集群发送广播消息 */
    public function broadcast($msg);

    /* 获取集群所有节点地址 */
    public function getClusterNodes();

    /* 加入集群 */
    public static function addMe();

    /* 退出集群 */
    public static function removeMe();
}