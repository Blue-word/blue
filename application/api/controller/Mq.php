<?php
namespace app\api\controller;

use think\Controller;

require_once ROOT_PATH.'/vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;

class Mq extends Controller{

	public function send()
	{
		// var_dump(APP_PATH);
		// var_dump(ROOT_PATH.'/vendor/autoload.php');die;
		$connection = new AMQPStreamConnection('localhost', '5672', 'guest', 'guest');
		$channel = $connection->channel();
		$channel->queue_declare('hello', false, false, false, false);

		$msg = new AMQPMessage('hello world!');
		$channel->basic_publish($msg, '', 'hello');
		echo "[x]发送'Hello World！'\ n";

		$channel->close();
		$connection->close();
	}

	public function receive($value='')
	{
		$connection = new AMQPStreamConnection('localhost', '5672', 'guest', 'guest');
		$channel = $connection->channel();
		$channel->queue_declare('hello', false, false, false, false);
		echo "[*]等待消息。退出按CTRL + C \ n";

		$callback = function($msg){
			echo "[x] Received'，$ msg-> body，“\ n";
		};
		$channel->basic_consume('hello', '', false, false, false, false, $callback);
		while ($channel->is_consuming()) {
			$channel->wait();
		}

		$channel->close();
		$connection->close();
	}
}