<?php

namespace Woocan\Lib;

/**
 * Class Template
 * 简易模板引擎，仅支持变量输出、foreach、for、if、include、函数等标签
 * 模板样例：
* <body>
    * <h1>{$header}</h1>
    * <div>
        * { foreach($list as $contents) }
            * {if(mb_strlen($contents,'UTF-8') > 20) }
                * <p>{:mb_substr($contents,0,20,'UTF-8')}...</p>
            * {else}
                * <p>{$contents}</p>
            * {/if}
        * {/foreach}
    * </div>
    * {include "footer.html"}
* </body>
 */
class TemplateEngine
{
	protected $config = [
	    'left_delimiter'    => '{',
        'right_delimiter'   => '}',
        'expire_time'       => 7200,
        'view_dir'          => null,
        'cache_dir'         => null,
        'tmpl_const'        => [],
    ];

	/// 构造方法对成员变量进行初始化
    function __construct($config)
    {
        if ($config) {
            $this->config = array_merge($this->config, $config);

            //检查目录是否可用
            if (!isset($this->config['view_dir']) || !isset($this->config['cache_dir'])) {
                throw new \Exception('view_dir & cache_dir needed!');
            }
            if ( !dirMake($this->config['cache_dir']) ) {
                throw new \Exception('模板编译目录不可用：'. $this->config['cache_dir']);
            }
        }
    }

	/// 展示缓存文件方法
	/// $viewName:模板文件名
    function fetch($viewName, $bindData, $viewDir=null)
    {
		$cachePath = $this->try2CreateCompileFile($viewName, $bindData, $viewDir);
		
		extract($bindData);
		ob_start();
		include $cachePath;
		$html = ob_get_clean() ; //获取并清空缓存区
		return $html;
    }
	
	/// 生成编译文件
	protected function try2CreateCompileFile($viewName, $bindData, $viewDir=null)
	{
		/// 拼接模板文件的全路径
        $viewDir = $viewDir ?: $this->config['view_dir'];
        $viewPath = rtrim($viewDir, '/') . '/' . $viewName;
        if (!file_exists($viewPath)) {
            throw new \Exception('模板文件'. $viewName .'不存在');
        }

	    /// 拼接缓存文件的全路径
        $cacheName = md5($viewName) . '.php';
        $cachePath = rtrim($this->config['cache_dir'], '/') . '/' . $cacheName;

	    /// 根据缓存文件全路径，判断缓存文件是否存在
        if (!file_exists($cachePath)) {
	        /// 编译模板文件
            $php = $this->compile($viewPath, $bindData);
	        /// 写入文件，生成缓存文件
            file_put_contents($cachePath, $php);
        } else {
            /// 如果缓存文件不存在， 编译模板文件，生成缓存文件
            /// 如果缓存文件存在，第一个，判断缓存文件是否过期，第二个，判断模板文件是否被修改过，如果模板文件被修改过，缓存文件需要重新生成
            $isTimeout = (filectime($cachePath) + $this->config['expire_time']) > time() ? false : true;
            $isChange = filemtime($viewPath) > filemtime($cachePath) ? true : false;

	        /// 缓存文件重新生成
            if ($isTimeout || $isChange) {
                $php = $this->compile($viewPath, $bindData);
                file_put_contents($cachePath, $php);
            } else {
                // 缓存文件存在，但include $xxxx 变量内的模板需要新编译
                $html = file_get_contents($cachePath);
                $html = $this->handleInclude($html, $bindData);
            }
        }
		return $cachePath;
	}

	/// compile方法，编译HTML文件
    protected function compile($file_name, $bindData)
    {
		//获取模板文件
	    $html = file_get_contents($file_name);
        //首先处理include标签
        $html = $this->handleInclude($html, $bindData);

		//正则转换数组：
        // %%表示(.*?)            匹配任意字符串
        // %s表示\s*              匹配>=0个空格
        // %v表示($^[a-zA-Z_].*?)  匹配变量
		$ctlKeys = [
		    ':%%' => '<?=\1?>',
			'%v' => '<?=\1 ?>',
			'foreach%s(%%)' => '<?php foreach (\1): ?>',
			'/foreach' => '<?php endforeach ?>',
			//'include %%' => '',
			'if%s(%%)' => '<?php if (\1): ?>',
            'elseif%s(%%)' => '<?php elseif (\1): ?>',
            'else%sif%s(%%)' => '<?php elseif (\1): ?>',
            'else' => '<?php else: ?>',
			'/if' => '<?php endif ?>',
			'for%s(%%)' => '<?php for (\1): ?>',
			'/for' => '<?php endfor ?>',
            'switch%s(%%)' => '<?php switch (\1): case -98514: break; ?>', //switch和第一个case之间不能有留空，所有要预设一个虚拟的
            '/switch' => '<?php endswitch ?>',
            'case%%' => '<?php case\1: ?>',
            '/case' => '<?php break; ?>',
            'default' => '<?php default: ?>',
            'php' => '<?php ',
            '/php' => ' ?>',
		];
		//遍历数组，生成正则表达式
		foreach ($ctlKeys AS $key=>$value) {
            $key = preg_quote($key, '#');
            $key = str_replace('%%', '(.+?)' , $key);
            $key = str_replace('%s', '\\s*' , $key);
            $key = str_replace('%v', '(\$[a-zA-Z_\[\]\'\"].*?)', $key);
            //添加首位控制符号，且允许两侧使用空格
			$key = '\\'. $this->config['left_delimiter']. ' *' .$key .' *'. '\\'.$this->config['right_delimiter'];
			$pattern = '#' . $key . '#';

			$html = preg_replace($pattern, $value, $html);
		}

		//替换模板常量
        if ($this->config['tmpl_const']) {
            $html = str_replace(array_keys($this->config['tmpl_const']), array_values($this->config['tmpl_const']), $html);
        }
		return $html;
    }

    protected function handleInclude($html, $bindData)
    {
        // 正则匹配include标签的处理函数
        $pregHandler = function($match) use($bindData) 
        {
            $filename = $match[1];

            // 文件名是否是变量
            if (strpos($filename, '$') === 0) {

                extract($bindData);
                $realFileName = ${substr($filename, 1)};
                // 将待包含文件生成缓存
                $this->try2CreateCompileFile($realFileName, $bindData);
                // include地址写变量而不能写死
                return '<?php include md5(' . $filename . ').".php" ?>';
            } else {
                $realFileName = trim($filename, '\'"');
                // 将待包含文件生成缓存
                $this->try2CreateCompileFile($realFileName, $bindData);
                // include地址写死
                $cacheName = md5($realFileName) . '.php';
                return '<?php include "' . $cacheName . '" ?>';
            }
        };

        // 根据compile文件中的include搜索
        $pattern = '#<\?php\\s*include\\s*md5\((.+?)\).*?\?>#';
        $html = preg_replace_callback($pattern, $pregHandler, $html);

        // 根据模板include标签搜索
        $pattern = '#\\'. $this->config['left_delimiter']. '\\s*include\\s*(.+?)\\s*'. '\\'.$this->config['right_delimiter']. '#';
        $html = preg_replace_callback($pattern, $pregHandler, $html);

        
        return $html;
    }
}