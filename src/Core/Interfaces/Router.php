<?php
namespace Woocan\Core\Interfaces;

interface Router
{
    /* 分发 */
    public function dispatch($rawParam);
}
