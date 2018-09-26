<?php
namespace Aphplication;
class Client {
	private $sockFile;
	private $socket;
	private $serverId;

	public function __construct($msgQueue = __DIR__ .'/queue') {
		set_error_handler(function($id, $msg) {
			throw new \Exception($msg);
		});


		if (!file_exists($msgQueue)) throw new \Exception('No queue file exists, is the server running?');
		$key = ftok($msgQueue, 'R');
		$this->queue = msg_get_queue($key ,0777);

	}

	public function connect() {
		$id = getmypid();
		$message = [$id, $GLOBALS];

		msg_send($this->queue, 100, $message, true, false);

		msg_receive($this->queue, $id, $msgtype, 1000000, $msg, true);
		foreach ($msg[0] as $header) header($header);
		return $msg[1];
	}
}


