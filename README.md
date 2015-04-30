# Aphplication - A lightweight php Application Server


Aphplication is a PHP application server. 

### Requirements

Aphplication requires a linux server with the sysvmsg.so extension enabled. This extension exists by default on mosts linux default installations.

### Usage

1) Create your server by creating a class that implements the Aphplication\Aphplication interfae

2) Pass an instance of this class to `Aphplication\Server()`;

3) Save this as a file e.g. `example1-persistence.php` 

```php
req
class MyApplication implements \Aphplication\Aphplication {
	private $num = 0;

	public function accept($appId, $sessionId, $get, $post, $server, $files, $cookie) {
		$this->num++;
		return $this->num;
	}
}

$server = new \Aphplication\Server(new MyApplication());
$server->start();
```

2) Start the application on the command line:

```
php MyApplication.php
```

3) Run the CLI Client script from the same directory that the server was started from (Both the server and the client *must* be started from the same current working directory)

```
php ../Aphplication/Client-CLI.php

```

This will connect to the server and the state is maintained across requests!



### Webservers 

For a webserver use Aphplication/Client.php as your entry point and start the server via the command line from the directory Client.php resides in. The directory is used as the base of the application so that you can run multiple applications on the same server.
