<?php
namespace Woocan\Lock;
/**
 * @author lht
 * 文件锁
 * 用于单台服务器的进程锁定，不支持分布式，但可始终随进程跟锁，更可靠
 */
use \Woocan\Core\Interfaces\Lock as ILock;
use \Woocan\Core\Context;

class File implements ILock
{
	private static $lock_dir = '';
	private static $lock_array = array();
	const KEY_PREFIX = '_lock';
	const Context_Key = 'context_locks';

	public function __construct($config)
	{
		self::$lock_dir = $config['filelock_path'];
		dirMake(self::$lock_dir);
	}
	/*
	 * 增加一个事务锁
	 */
	public function Lock($uid,$key, $expire=60)
	{
		$retry = 5;
		for ($i = 1; $i <= $retry; $i++) {
			$ret = self::addLock($uid,$key,$expire);
			if ($ret){
				return true;
			}
			else {
				Context::sleep(0.5); // wait for 0.5 seconds
			}
		}
		return false;
	}

	/*
	 * 删除一个事务锁
	 */
	public function unLock($uid,$key)
	{
		return self::deleteLock($uid,$key);
	}


	private static function _getFileName($uid,$key)
	{
		return $uid.$key.'.lock';
	}

	 
	private static function addLock($uid,$key,$lock_time){
		$fileName = self::_getFileName($uid, $key);
		$fileHandle = self::_getFileHandle($fileName);
		$lock = $fileHandle && flock($fileHandle, LOCK_EX | LOCK_NB);
		if ($lock ===  TRUE){
			self::$lock_array[$fileName] = array('file' => $fileHandle);
			$content = microtime(true). PHP_EOL . $uid.PHP_EOL . $key . PHP_EOL . $lock_time;
			if (fwrite($fileHandle, $content) === FALSE) {
				flock($fileHandle, LOCK_UN);    // 释放锁定
				return false;
			}
			fflush($fileHandle);

			//记录到上下文
			$lockList = Context::get(self::Context_Key) ?? [];
			$lockList[$fileName] = 1;
			Context::set(self::Context_Key, $lockList);

			return true;
		}
		return false;
	}

	private static function deleteLock($uid,$key){
		$fileName = self::_getFileName($uid,$key);
		$lock_file =  self::_getPath($fileName);
		if (file_exists($lock_file)){
			$fileHandle = self::_getFileHandle($fileName);
			$fileHandle && flock($fileHandle, LOCK_UN);
			ftruncate($fileHandle, 0);
			fclose($fileHandle);
			unset(self::$lock_array[$fileName]);
		}

		$lockList = Context::get(self::Context_Key) ?? [];
		unset($lockList[$fileName]);
		Context::set(self::Context_Key, $lockList);
		
		return true;
	}

	private static function _getFileHandle($name){
		$fileHandle = null;
		if (isset(self::$lock_array[$name]))	{
			$fileHandle = self::$lock_array[$name]['file'];
		}

		if ($fileHandle == null) {
			$file = self::_getPath($name);
			if (!file_exists($file)){
				$fileHandle = fopen($file, 'w+');
				@chmod($file, 0777);
			} else {
				$fileHandle = fopen($file, 'w+');
			}
		}
		return $fileHandle;
	}

	private static function _getPath($name)
	{
		return self::$lock_dir.'/'.$name;
	}

	/* swoole工厂模式不会执行 */
	function __destruct()
	{
		foreach (self::$lock_array as $key => $value) {
			flock($value['file'], LOCK_UN);
		}
	}

	private function _getKey($uid,$key)
	{
		return self::KEY_PREFIX.$uid."_lock".$key;
	}
}