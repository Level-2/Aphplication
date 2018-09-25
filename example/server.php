<?php
require '../Aphplication/Aphplication/Server.php';
require '../Aphplication/Aphplication/Aphplication.php';

//This class is executed once and keeps running in the background
class MyApplication implements \Aphplication\Aphplication {
	// State that is maintained across reuqests. This is not serialised, it is kept-as is so can be
	// Database connections, complex object graphs, etc
	private $num = 0;

	// The accept method is executed on each request. Because this instance is already running, the superglobals are passed from the client
	public function accept(): string {
		// The only code that is run on each request.
		return $this->num++;
	}
}

$server = new \Aphplication\Server(new MyApplication());
if (isset($argv[1]) && $argv[1] == 'stop') $server->shutdown();
else if (isset($argv[1]) && $argv[1] == 'reload') {
	 $server->shutdown();
	 sleep(1);
	 $server->start();
}
else $server->start();