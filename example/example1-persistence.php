<?php
namespace Example;
require_once '../Aphplication/Aphplication.php';

class MyApplication implements \Aphplication\Aphplication {
	private $num = 0;

	public function accept($appId, $sessionId, $get, $post, $server, $files, $cookie) {
		$this->num++;
		return $this->num;
	}
}

$server = new \Aphplication\Server(new MyApplication());
$server->start();