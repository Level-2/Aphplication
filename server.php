<?php
namespace Level2\EntryPoint;
require_once 'aphplication.php';

class MyApplication implements \Aphplication\Aphplication {
	private $dic;
	private $conf;
	private $sessionName;
	private $pdo;

	public function __construct() {
		chdir('../framework');
		require_once 'Conf/Core.php';
		$this->conf = new \Config\Core;

		foreach ($this->conf->autoInclude as $file) require_once $file;

		//Create the DIC
		$this->dic = new $this->conf->dic(new $this->conf->dicConfig);

		//Use the DIC to consturct the autoloader
		$autoLoader = $this->dic->create($this->conf->autoloader);

	}

	public function accept($appId, $sessionId, $get, $post, $server, $files, $cookie) {
		session_id($sessionId);
		try {
			ob_start();
			$entryPoint = $this->dic->create($this->conf->entryPoint, [$get, $post, $server]);
			$buffer = ob_get_clean();
			$output = $buffer . $entryPoint->output();
		}
		catch (\Exception $e) {
			$output = $e->getMessage();
		}
		return $output;
	}
}


set_error_handler(function ($errno, $errstr, $errfile, $errline ) {
    if (!(error_reporting() & $errno)) {
        // This error code is not included in error_reporting
       return;
    }
    throw new \ErrorException($errstr, 0, $errno, $errfile, $errline);
});


//Implement this with a class that wraps a real DIC to give it a consistent API. This allows using any DIC 
interface Dic {
	public function __construct($conf);
	public function create($class, array $args = []);
}


interface EntryPoint {
	public function output();	
}

$server = new \Aphplication\Server(new MyApplication());
$server->start();