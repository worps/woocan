<?php
namespace Woocan\Core\Interfaces;

interface Cache
{
    function set($key, $value, $timeOut);

    function get($key);

    function delete($key);

    function increment($key, $step = 1);

    function decrement($key, $step = 1);

    function clear();
}