<?php
/**
 * @author lht
 * Date: 13-6-17
 */
namespace Woocan\Core\Interfaces;

interface Server
{
    public function getServ();

    public function run();

    public function isEnableCo();
}
