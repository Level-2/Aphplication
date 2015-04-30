<?php
namespace Aphplication;
interface Aphplication {
	public function accept($appId, $sessionId, $get, $post, $server, $files, $cookie);
}

class Server {
	private $queue;
	private $file;
	private $numThreads = 12;
	private $application;

	public function __construct(Aphplication $application, $file = __DIR__ . '/queue') {
		$this->file = $file;
		$this->application = $application;
	}

	public function setNumThreads($num) {
		$this->numThreads = $num;
	}

	public function start() {
		set_time_limit(0);

		
		if (file_exists($this->file)) unlink($this->file);
		file_put_contents($this->file, '');

		$this->queue = msg_get_queue(ftok($this->file, 'R'), 0777);
		msg_set_queue($this->queue, []);
		msg_remove_queue($this->queue);

		$this->queue = msg_get_queue(ftok($this->file, 'R'),0777);
 		$this->listen();
	}

	private function createFork($msgid, $lifetime = 10) {
		$pid = pcntl_fork();
		sleep(0.001);
		if (!$pid) {
			set_time_limit($lifetime);
			while (true) {
				//Remove previously sent headers
				header_remove();
				msg_receive($this->queue, $msgid, $msgtype, 1024*50, $message, true);				
				if ($message == 'shutdown') exit();
				list($id, $data) = $message;

				$output = $this->application->accept($msgid, $data['sessionId'], $data['get'], $data['post'], $data['server'], $data['files'], $data['cookie']);
				msg_send($this->queue, $id, [headers_list(), $output], true, true);
				
				//Force GC to prevent memory leaks! Without this, the process will grow and grow
				gc_collect_cycles();
			}
		}
	}

	private function listen() {
		//Use twice the number of available threads, some operations e.g. queries cause a wait where the cpu isn't being used
		//By allowing twice the threads, concurrency is improved
		for ($i = 0; $i < $this->numThreads*2; $i++) {
			$this->createFork(100+$i, 0);
		}
	}

	public function shutdown() {
		$this->queue = msg_get_queue(ftok($this->file, 'R'),0777);
		for ($i = 0; $i < $this->numThreads*2; $i++) {
			msg_send($this->queue, 100+$i, 'shutdown');
		}
		
	}
}
