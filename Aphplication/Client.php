<?php
namespace Aphplication;
class Client {
	private $sockFile;
	private $socket;
	private $serverId;

	public function __construct($id, $msgQueue = __DIR__ .'/queue') {
		set_error_handler(function($id, $msg) {
			throw new \Exception($msg);
		});
		try {

			if (!file_exists($msgQueue)) throw new \Exception('No queue file exists, is the server running?');
			$key = ftok($msgQueue, 'R');
			$this->queue = msg_get_queue($key ,0777);
		}
		catch (\Exception $e) {
			throw new \Exception('Could not connect to App server: ' . $e->getMessage());
		}
	}

	public function sendMessage($data) {
		//Generate a random ID for this request
		$id = rand();

		$message = [$id, $data];

		msg_send($this->queue, 100 + rand(0, 23), $message, true, false);

		msg_receive($this->queue, $id, $msgtype, 1000000, $msg, true);
		foreach ($msg[0] as $header) header($header);
		return $msg[1];
	}
}


$client = new Client(0);
session_write_close();
echo $client->sendMessage($GLOBALS);
