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
		ini_set('html_errors', true);

		if (file_exists($this->file)) unlink($this->file);
		file_put_contents($this->file, '');

		$this->queue = msg_get_queue(ftok($this->file, 'R'),0777);
 		$this->listen();
	}

	//This function could be broken up or even moved to a Fork class but anything inside the while
	//loop is run on every request. To maximise server performance it is kept as simple as possible. TODO: Benchmark, does this really matter?
	private function createFork() {
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

				//If the browser sent a session cookie, use the supplied session ID
				// If the browser did not specify a session ID, reset session id to null so PHP starts a new session if it's required
				$sessionId = $data['_COOKIE'][$sessionName] ?? null;
				session_id($sessionId);

				ob_start();
				$output = $this->application->accept();
				$output = ob_get_clean() . $output;

				$headers = headers_list();
				// If session_id() has changed since the page loaded, session_start() for a new session or session_regenerate_id() have been called and the cookie needs updating
				if (session_id() !== $sessionId) {
					$headers[] = 'Set-Cookie: '. $sessionName . '=' . session_id() . '; path=/';
				}
				//Send the output back to the client based on its ID
				msg_send($this->queue, $id, [$headers, $output], true, true);

				//If there is a session active, close it. Otherwise the session remains open and locked in this thread and cannot be opened in another one.
				//This adds a slight performance overhead but most scripts to not explicitly close the session.
				if (session_status() == \PHP_SESSION_ACTIVE) {
					session_write_close();
				}
				//Force GC to prevent memory leaks! Without this, the process will grow and grow
				gc_collect_cycles();
			}
		}
	}

	private function listen() {
		//Use twice the number of available threads, some operations e.g. queries cause a wait where the cpu isn't being used
		//By allowing twice the threads, concurrency is improved
		for ($i = 0; $i < $this->numThreads*2; $i++) {
			$this->createFork();
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
			msg_send($this->queue, 100, 'shutdown', true, true);
			sleep(0.01);
		}
		unlink($this->file);
		$this->print('Shutdown: Complete');
	}

	private function print($text) {
		// Use STDERR, if the server prints *anything* to STDOUT (e.g. echo), it causes errors any time php sets a header
		ob_start();
		ini_set('html_errors', false);
		var_dump($text);

		fwrite(STDERR, ob_get_clean() . "\n");
		ini_set('html_errors', true);
	}
}

