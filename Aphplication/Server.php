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

		$this->queue = msg_get_queue(ftok($this->file, 'R'),0777);
 		$this->listen();
	}

	private function createFork($i) {
		$sessionName = session_name();
		$pid = pcntl_fork();
		sleep(0.001);
		if (!$pid) {
			set_time_limit(0);
			while (true) {
				//Remove previously sent headers
				header_remove();

				msg_receive($this->queue, 100, $msgtype, 1024*50, $message, true);
				if ($message == 'shutdown') {
					$this->print('Worker shutting down');
					exit();
				}
				//TODO: CHUNKING list($id, $remainingChunks, $data) = $message;
				list($id, $data) = $message;

				//$GLOBALS = $data; does not work, the superglobals $_GET etc are not set.
				foreach ($data as $key => $val) $GLOBALS[$key] = $val;

				//Update the session ID from the cookie
				$sessionId = $data['_COOKIE'][$sessionName] ?? null;
				if ($sessionId) {
					session_id($sessionId);
				}
				// If there is no current session, generate a unique ID. If not, different requests will share the same session id. The script will use the last real ID even if it's a new user
				else session_id(md5(uniqid()));

				$output = $this->application->accept();

				$headers = headers_list();
				// If session_id() has changed since the page loaded, session_start() for a new session or session_regenerate_id() have been called and the cookie needs updating
				if (session_id() !== $sessionId) {
					$headers[] = 'Set-Cookie: '. $sessionName . '=' . session_id() . '; path=/';
				}
				//If there is a session active, close it. Otherwise the session remains open and locked in this thread and cannot be opened in another one.
				if (session_status() == \PHP_SESSION_ACTIVE) {
					session_write_close();
				}
				msg_send($this->queue, $id, [$headers, $output], true, true);

				//Force GC to prevent memory leaks! Without this, the process will grow and grow
				gc_collect_cycles();
			}
		}
	}

	private function listen() {
		//Use twice the number of available threads, some operations e.g. queries cause a wait where the cpu isn't being used
		//By allowing twice the threads, concurrency is improved
		for ($i = 0; $i < $this->numThreads*2; $i++) {
			$this->createFork($i);
			$this->print('Creating worker ' . $i);
		}
	}

	public function shutdown() {
		if (!is_file($this->file)) {
			$this->print('Shutdown: Nothing to do, server is not running.');
			return;
		}

		$this->queue = msg_get_queue(ftok($this->file, 'R'),0777);

		for ($i = 0; $i < $this->numThreads*4; $i++) {
			//Send non-blocking shutdown request. Different threads will pick it up at different times.
			msg_send($this->queue, 100, 'shutdown');
			sleep(0.01);
		}
		unlink($this->file);
		$this->print('Shutdown: Complete');
	}

	private function print($text) {
		// Use STDERR, if the server prints *anything* to STDOUT (e.g. echo), it causes errors any time php sets a header
		ob_start();
		var_dump($text);

		fwrite(STDERR, ob_get_clean() . "\n");
	}
}

