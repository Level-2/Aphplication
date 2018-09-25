<?php
namespace Aphplication;
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

	private function createFork($msgid, $lifetime = 0) {
		$pid = pcntl_fork();
		sleep(0.001);
		if (!$pid) {
			set_time_limit($lifetime);
			while (true) {
				//Remove previously sent headers
				header_remove();
				msg_receive($this->queue, $msgid, $msgtype, 1024*50, $message, true);
				if ($message == 'shutdown') {
					$this->print('Worker shutting down');
					exit();
				}
				list($id, $data) = $message;

				//$GLOBALS = $data; does not work, the superglobals $_GET etc are not set.
				foreach ($data as $key => $val) $GLOBALS[$key] = $val;

				$output = $this->application->accept($msgid);
				msg_send($this->queue, $id, [headers_list(), $output], true, true);

				//Force GC to prevent memory leaks! Without this, the process will grow and grow
				gc_collect_cycles();

				//If there is a session active, close it. Otherwise the session remains open and locked in this thread and cannot be opened in another one.
				if (session_status() == \PHP_SESSION_ACTIVE) {
					session_write_close();
				}
			}
		}
	}

	private function listen() {
		//Use twice the number of available threads, some operations e.g. queries cause a wait where the cpu isn't being used
		//By allowing twice the threads, concurrency is improved
		for ($i = 0; $i < $this->numThreads*2; $i++) {
			$this->createFork(100+$i, 0);
			$this->print('Creating worker ' . $i);
		}
	}

	public function shutdown() {
		if (!is_file($this->file)) {
			$this->print('Shutdown: Nothing to do, server is not running.');
			return;
		}

		$this->queue = msg_get_queue(ftok($this->file, 'R'),0777);
		for ($i = 0; $i < $this->numThreads*2; $i++) {
			msg_send($this->queue, 100+$i, 'shutdown');
		}
		//Wait briefly before deleting the file to ensure that all threads have shutdown correctly.
		sleep(0.5);
		unlink($this->file);
		$this->print('Shutdown: Complete');
	}

	private function print($text) {
		// Use STDERR, if the server prints *anything* to STDOUT (e.g. echo), it causes errors any time php sets a header
		fwrite(STDERR, $text . "\n");
	}
}

