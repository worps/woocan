
<h2>注意事项</h2>

【协程】 
1.协程客户端需要在协程环境中执行
如Request中可以直接使用协程，因为swoole的Request本身已在协程处理
$server->on('Request', function($request, $response) {
	$mysql = new Swoole\Coroutine\MySQL();
但
$server->on('WorkerStart', function() {
	$mysql = new Swoole\Coroutine\MySQL(); //错误
	go(function(){
        $mysql = new Swoole\Coroutine\MySQL(); //正确
    });
2.Swoole 的协程是基于单进程的, 无法像golang利用多核CPU。
因此在需要大量计算时，不断开启协程处理计算并无效果。


【抢占式】
fd发来的tcp数据流可能会传送到不用worker，这时worker无法进行数据拼接

【其他】
1.swoole提供的协程redis不支持以下指令
scan object sort migrate hscan sscan zscan
2.swoole不支持set_exception_handler
表现为设置后无效果
3.swoole和redis/mysql长链接时socket_stream_timeout设置无效
如ini_set('default_socket_timeout', 2)表示socket流从建立到
传输再到关闭整个过程必须要在这个参数设置的3s内完成，该设置仅限于短连接。
4.连接池中pop的对象不能被多协程共享，因此不能将该连接对象放到全局变量、类的元素中。


【2023.06.25更新】
1.优化crontab使用方式（对应Frame未更新）
入口文件放在项目根目录，开启auto_route后即可 `php entrance /test/main`。
建议在对应api文件中使用形式： `function main($arg1, ...)`
2.取消原固定apps目录，改为app_项目名；增加多模块配置。
3.增加默认页路由配置，如
['path'=>'/', 'class'=>'index', 'method'=>'main']
4.优化缺少参数、参数错误、路由错误的报错级别，防止cli模式执行错路由不报错。
可以根据实际通过`unexcept_codes`配置选择性关闭
5.修复pop出的pdo对象执行table()后会污染上次pop出的pdo的bug。
6.移除cluster功能
7.连接池不再初始化一定数量的连接
8.优化错误和异常的输出