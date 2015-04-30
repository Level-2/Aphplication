<?php
namespace Aphplication;
class Client {
	private $sockFile;
	private $socket;
	private $serverId;

	public function __construct($serverId) {
		$key = ftok(__DIR__ .'/queue', 'R');
		$this->queue = msg_get_queue($key ,0777);
		$this->serverId = $serverId;
	}

	public function sendMessage($data) {
		//Generate a random ID for this request
		$id = rand();

		$message = [$id, $data];

		msg_send($this->queue, $this->serverId, $message, true, false);	

		msg_receive($this->queue, $id, $msgtype, 1000000, $msg, true);
		foreach ($msg[0] as $header) header($header);
		return $msg[1];
	}
}

session_start();

if (!isset($_SESSION['__appserverId'])) {
	$_SESSION['__appserverId'] = 100 + rand(0, 23);
}
$client = new Client($_SESSION['__appserverId']);
session_write_close();
echo $client->sendMessage(['sessionId' => session_id(), 'get' => $_GET, 'post' => $_POST, 'server' => $_SERVER, 'files' => $_FILES, 'cookie' => $_COOKIE]);
