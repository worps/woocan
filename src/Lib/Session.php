<?php
namespace Woocan\Lib;

use \Woocan\Core\Factory;
use \Woocan\Core\Request;
use \Woocan\Core\Response;
use \Woocan\Core\Context;

class Session
{
    // 非fastCGI时用yac存储session
    // session对应的cookie名
    const Session_Cookie_Name = 'lht_session';
    const Session_Expire = 7200;
    // 缓存
    private static $cacher;

    public static function init()
    {
        if (C('server_mode') === 'Fastcgi') {
            // session跨域
            if (C('project.cookie_cors')) {
                ini_set('session.cookie_secure', 1);
                ini_set('session.cookie_samesite', 'None');
            }
            session_start();
        }
    }

    /* 获取session */
    public static function get($key)
    {
        if (C('server_mode') === 'Fastcgi') {
            return $_SESSION[$key] ?? null;
        }
        $session_id = self::_getSessionId();
        $data = self::_assistCacher()->get($session_id);
        return $data[$key] ?? null;
    }

    /* 设置session */
    public static function set($key, $value)
    {
        if (C('server_mode') === 'Fastcgi') {
            $_SESSION[$key] = $value;
            return;
        }

        $session_id = self::_getSessionId();
        $cacher = self::_assistCacher();
        $data = $cacher->get($session_id) ?: [];

        $data[$key] = $value;
        $cacher->set($session_id, $data, self::Session_Expire);
    }

    /* 删除session */
    public static function delete($key)
    {
        if (C('server_mode') === 'Fastcgi') {
            unset($_SESSION[$key]);
            return;
        }
        $session_id = self::_getSessionId();
        $cacher = self::_assistCacher();
        $data = $cacher->get($session_id) ?: [];

        unset($data[$key]);
        $cacher->set($session_id, $data, self::Session_Expire);
    }

    /* 清空当前用户session */
    public static function destory()
    {
        if (C('server_mode') === 'Fastcgi') {
            session_destroy();
            return;
        }
        $session_id = self::_getSessionId();
        self::_assistCacher()->delete($session_id);
    }

    /* 获取YAC辅助缓存器 */
    private static function _assistCacher()
    {
        if (self::$cacher) {
            return self::$cacher;
        }
        if (!\extension_loaded('yac')) {
            throw new \Woocan\Core\MyException('FRAME_SYSTEM_ERR', "session need YAC extension");
        }
        $cacher = Factory::getInstance('\\Woocan\\Cache\\Yac', ['prefix'=>'_woocan_session']);
        return $cacher;
    }

    /* 获取session_id */
    private static function _getSessionId()
    {
        $sessionId = Request::getHttpInfo('cookie', self::Session_Cookie_Name);
        if (!$sessionId) {
            //防止第一次请求时重复生成session_id
            $sessionId = Context::baseCoGet(self::Session_Cookie_Name);
            if ($sessionId) {
                return $sessionId;
            }
            //第一次生成session_id
            $sessionId = session_create_id();
            Context::baseCoSet(self::Session_Cookie_Name, $sessionId);
            Response::setCookie(self::Session_Cookie_Name, $sessionId, self::Session_Expire);
        }
        return $sessionId;
    }
}