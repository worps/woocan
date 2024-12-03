<?php
namespace Woocan\Lib;

use \Woocan\Core\Factory;
/**
 * 像linux的crontab格式一样，定时执行作业
 * 仅支持linux环境
 */
class CronManager
{
	/**
	 * 启动crontab
	 * crontab格式：
		* [
		*	// 路由=>cron同linux格式配置，start开始运行日期（可选），end截至运行日期（可选）
		*	'test/main' => ['cron'=>'* * * * *', 'start'=>'2020-05-18 10:24', 'end'=>'2020-05-18 10:26'],
		*]
	 */
	public static function run($crontab)
	{
		//解析配置表为程序易读格式
        $cronParseList = self::_init_parse_crons($crontab);
		//尝试创建日志目录
		$logDir = C('project.log_path');
		dirMake($logDir);

        while (true) {
			$strTime = date('Y-m-d H:i');
            $timestamp = strtotime($strTime);
			$date = getdate($timestamp);

            foreach ( $cronParseList as $router => &$parseInfo)
            {
                if ($timestamp >= $parseInfo['start_time'] && 
					$timestamp <= $parseInfo['end_time'] && 
					$timestamp > $parseInfo['last_execute_time'] && 
					self::isExecuteDate($parseInfo['range'], $date)
				) {
                    self::_run($router);
					$parseInfo['last_execute_time'] = $timestamp;
                }
            }

            //下一分钟
            $sleepSecond = 60 - floor(date('s'));
            sleep($sleepSecond);
        }
	}

	/**
	 * 子进程中执行某个作业（pcntl方式）
	 * 对比：
	 * 1.pcntl方式，支持共享内存（yac）但不支持windows
	 * 2.exec/popen方式，支持windows且支持非php进程，但不支持共享内存（yac）
	 */
    private static function _run($router)
    {
		$pid = pcntl_fork();
		if ($pid == 0) //子进程
		{
			//输出重定向
			ob_start(function($str) {
				$target = C('project.log_path'). 'cronhistory'. date('Ymd'). '.log';
				\file_put_contents($target, $str . "\n", FILE_APPEND);
			});
			//跳转到router
			$routerParam = parseQuery($router);
			Factory::getInstance('\\Woocan\\Router\\Api')->dispatch($routerParam);
			exit();
		}
		else { //主进程
			$pid = pcntl_wait($status, WUNTRACED); //取得子进程结束状态
			if (pcntl_wifexited($status)) {
				//echo "\n* Sub process: {$pid} exited with {$status}\n";
			}else{
				//非正常退出
				$code = pcntl_wexitstatus($status);
				echo "!!!service stop code: $code;pid is $pid";
			}
		}
    }

	//解析定时作业列表，生成为程序易读格式
    private static function _init_parse_crons($crontab)
    {
        $cronParseList = [];

        foreach ( $crontab as $path => $cronInfo) {
            $range = self::parseCronStr($cronInfo['cron']);
            $startTime = isset($cronInfo['start']) ? strtotime($cronInfo['start']) : 0;
            $endTime = strtotime( $cronInfo['end'] ?? "+10 year" );

            $cronParseList[ $path ] = [
                'range' => $range,
                'start_time' => $startTime,
                'end_time'   => $endTime,
				'last_execute_time' => 0,
            ];
        }
        return $cronParseList;
    }

	/**
	 * 检查解析后的时间格式 是否该执行了
	 */
	public static function isExecuteDate($range, $date)
	{
		if (isset($range['minutes'][ $date['minutes'] ]) &&
			isset($range['hours'][ $date['hours'] ]) &&
			isset($range['mday'][ $date['mday'] ]) &&
			isset($range['mon'][ $date['mon'] ]) &&
			isset($range['wday'][ $date['wday'] ])
		) {
			return true;
		}
		return false;
	}
	
	/**
	 * 将linux crontab格式解析为本程序可读格式
	 */
	public static function parseCronStr($cronStr) {
		list($minutes, $hours, $mday, $mon, $wday) = explode(' ', $cronStr);

		$mon = strtr(strtolower($mon), array(
			'jan' => 1,
			'feb' => 2,
			'mar' => 3,
			'apr' => 4,
			'may' => 5,
			'jun' => 6,
			'jul' => 7,
			'aug' => 8,
			'sep' => 9,
			'oct' => 10,
			'nov' => 11,
			'dec' => 12,
		));

		$wday = strtr(strtolower($wday), array(
			'sun' => 0,
			'mon' => 1,
			'tue' => 2,
			'wed' => 3,
			'thu' => 4,
			'fri' => 5,
			'sat' => 6,
		));

		$range = array(
			'minutes'   => self::_parse_crontab_field($minutes, 0, 59),
			'hours'     => self::_parse_crontab_field($hours, 0, 23),
			'mday' => self::_parse_crontab_field($mday, 1, 31),
			'mon'    => self::_parse_crontab_field($mon, 1, 12),
			'wday'  => self::_parse_crontab_field($wday, 0, 7)
		);

		// 周7变周0
		if (end($range['wday']) === 7)
		{
			array_pop($range['wday']);

			if (reset($range['wday']) !== 0)
			{
				array_unshift($range['wday'], 0);
			}
		}
		
		//反转数组，提高isExecuteDate的查询效率
		foreach ($range as $key => $items) {
			$range[ $key ] = array_flip($items);
		}
		
		return $range;
	}
	
	/**
	 * Returns a sorted array of all the values indicated in a Crontab field
	 * @link http://linux.die.net/man/5/crontab
	 *
	 * @param   string  Crontab field
	 * @param   integer Minimum value for this field
	 * @param   integer Maximum value for this field
	 * @return  array
	 */
	protected static function _parse_crontab_field($value, $min, $max)
	{
		$result = array();

		foreach (explode(',', $value) as $value)
		{
			if ($slash = strrpos($value, '/'))
			{
				$step = (int) substr($value, $slash + 1);
				$value = substr($value, 0, $slash);
			}

			if ($value === '*')
			{
				$result = array_merge($result, range($min, $max, $slash ? $step : 1));
			}
			elseif ($dash = strpos($value, '-'))
			{
				$result = array_merge($result, range(max($min, (int) substr($value, 0, $dash)), min($max, (int) substr($value, $dash + 1)), $slash ? $step : 1));
			}
			else
			{
				$value = (int) $value;

				if ($min <= $value AND $value <= $max)
				{
					$result[] = $value;
				}
			}
		}

		sort($result);

		return array_unique($result);
	}
	
}



?>