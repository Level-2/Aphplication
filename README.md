# Aphplication - A lightweight php Application Server


Aphplication is a PHP application server. This works in a similar way to Node.js, your application is aways running and when someone connects, they are connecting to the active application. This allows you to maintain state across requests and avoid a lot of the bootstrapping code that exists on each request.

There are two parts to an Aphplication project:

1) The server. This contains all of your code and keeps running even when nobody is visiting the page.

2) The client. This is a middle-man between the browser and the running application. When someone connects to `client.php` the PHP script runs, talks to the server, asks the server to do some processing and then returns the result. The server keeps running but the client script stops.

## Requirements

Aphplication uses message queues internally. This is considerably faster than sockets which are used by other similar tools. You must have the `extension=sysvmsg.so` uncommented in `php.ini`.


Aphplication requires a linux server with the sysvmsg.so extension enabled. This extension exists by default on mosts linux default installations.

## Usage

1) Create your server by creating a class that implements the Aphplication\Aphplication interface

2) Pass an instance of this class to `Aphplication\Server()`;

3) Save this as a file e.g. `example1-persistence.php` 

```php

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
php example1-persistence.php
```

3) Run the CLI Client script from the same directory that the server was started from (Both the server and the client *must* be started from the same current working directory)

```
php ../Aphplication/Client-CLI.php

```

This will connect to the server and the state is maintained across requests!



### Shutting down the server

To shut down the server run the same script via command line with the stop command:

```php
php example1-persistence.php stop
```

### Webserver example

First create a web server, `server.php`. This does not need to be in a `public_html` directory or anywhere that is web-accessible:


```php
require 'vendor/autoload.php';
class MyApplication implements \Aphplication\Aphplication {
	//Application state. This will be kept in memory when the application is closed
	//This can even be MySQL connections and other resources

	private $num;

	/*
	The `accept` function takes several arguments:

	$appId - a unique identifier for this thread
	$sessionId - the users session id
	$get - $_GET from the client
	$post - $_POST from the client
	$server - $_SERVER from the client
	$files - $_FILES from the client
	$cookie - $_COOKIE from the client
	*/

	public function accept($appId, $sessionId, $get, $post, $server, $files, $cookie) {
		$this->num++;

		//return the response to send back to the browser, e.g. some HTML code
		return $this->num;
	}

}


//Now create an instance of the server 
$server = new \Aphplication\Server(new MyApplication());

//Check argv to allow starting and stopping the server
if (isset($argv[1]) && $argv[1] == 'stop') $server->shutdown();
else $server->start();
```

Once the server has been written, start it using

```
php server.php
```

This will start a PHP process in memory and start waiting for requests. To stop the server you can call `php server.php stop`

Now that the server is running, create a `client.php` inside the `public_html` or `httpdocs` folder, somewhere that is web-accessible.

`client.php` should contain only this code:

```php
require_once '../Aphplication/Client-CLI.php';
```

(Adjust the path to `Aphplication/Client.php` accordingly). You **could** use composer's autoloader for this, however it's not a good idea as composers autoload is a significant overhead for loading a single file. You'll get better perfomance just using `require` to include the supplied client code. 

The supplied client code connects to the server, sends it the get/post/etc data from the current request and returns the response. This PHP file **is** run on every request so try to keep it light!


## Performance

Aphplication can be up to 1000% faster than a standard PHP script. When you run a Laravel, Wordpress or Zend project the PHP interpreter is executed and does a lot of work: Loading all the required.php files, connecting to the database and finally processing your request. With Aphplication, all that boostrapping code is done once, when the server starts. When someone connects they are connecting to the running application that's already done all that boostrapping work, the server then just processes the request and hands it off to the client. 

You can think of the Application server a bit like MySQL, it's always running and waiting to handle requests. When a request is made, it does some processing and returns the result. Unlike a traditional PHP script, it keeps running ready to handle the next request.

This gives Aphplication a huge performance beneifit over the traditional method of loading all the required files and making all the necessary connections on each request.


### Multithreading

Aphplication is multi-threaded. It will launch as many processes as you like. This should be up to 3x the number of cores (or virtual cores) your CPU has. This is because often PHP scripts pause (e.g. while waiting for MySQL to return some data). 

You can set the number of threads by using

```php
$server->setThreads($number);
```

before `$server->start()`