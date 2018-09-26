# Aphplication - A lightweight php Application Server


## PHP is slow by nature becauase everything is done on every request

Normally  when you run a PHP script the following happens:

![Without an app server](https://r.je/img/aphplication-without.png)


The only thing that happens differently on each request is the final step. All the (non-trivial) work of boostrapping the application is done on every single request. Each time a page is viewed, the classes are loaded, the framework is instantiated, database connected to, libraries configured. All of this hard work is done on every request.  Every time you visit a page all the config files are loaded and classes instantiated.


Aphplication is an attempt to solve this problem by changing the nature of the way PHP handles requests.

What if we could take a snapshot of a PHP script at step 5, after everything is boostrapped and ready to handle the individual requests? This is how Aphplication works:

![Without an app server](https://r.je/img/aphplication-with.png)

By using Aphplication, the code that is normally run on each request is run once and then listens for connections. You can effectively jump in to any part of a running PHP script.


The result is that each request will only perform the tasks it needs. [This gives a 2400% performance increase in Laravel](https://laracasts.com/discuss/channels/laravel/proof-of-concept-application-server-2400-laravel-startup-speed-increase)



## Aphplication


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

3) Save this as a file e.g. `server.php`

```php
//This class is executed once and keeps running in the background
class MyApplication implements \Aphplication\Aphplication {
	// State that is maintained across reuqests. This is not serialised, it is kept-as is so can be
	// Database connections, complex object graphs, etc
	// Note: Each worker thread has a copy of this state by default
	private $num = 0;

	// The accept method is executed on each request. Because this instance is already running, the superglobals are passed from the client

	//The return value is a string which is to be sent back to the client.
	//Note: For better comatibility any header() calls are also sent back to the client
	public function accept(): string {
		// The only code that is run on each request.
		$this->num++;
		return $this->num;
	}
}

$server = new \Aphplication\Server(new MyApplication());
$server->start();
```

The server will now run and the single `MyApplication` instance will be kept running in a PHP process on the server. Each time the client connects, the server's `accept` method is called and can do the specific processing for the page.

Which allows you to do something like this:


```php
//This class is executed once and keeps running in the background
class MyApplication implements \Aphplication\Aphplication {
	private $frameworkEntryPoint;

	public function __construct() {
		// Instantiate the framework and store it in memory. This only happens once and is kept active on the server
		$db = new PDO('...');
		$this->frameworkEntryPoint = new MyFramework($db);
	}

	// The accept method is executed on each request. Because this instance is already running, the superglobals are passed from the client

	//The return value is a string which is to be sent back to the client.
	//Note: For better comatibility any header() calls are also sent back to the client
	public function accept(): string {
		// Each time a client requests, route the request as normal
		return $this->frameworkEntryPoint->route($_SERVER['REQUEST_URI']);
	}
}

$server = new \Aphplication\Server(new MyApplication());
$server->start();
```


By doing this, all your framework classes are only ever loaded once. This is even better than opcaching because not only are files only parsed once, the bootstrap code is only ever executed once.

2) Start the application on the command line:

Assuming your server is stored in `server.php` start the app server:

```
php server.php
```

3) Run the CLI Client script from the same directory that the server was started from (Both the server and the client *must* be started from the same current working directory)

Now connect to the server from the client.

```
require '../Aphplication/Client.php';
$client = new \Aphplication\Client();
echo $client->connect();

```

To use a web server as a client simply create the PHP script:

```php
require '../Aphplication/Client.php';
$client = new \Aphplication\Client();
echo $client->connect();
```



### Shutting down the server

To shut down the server run the same script via command line with the stop command:

```php
php server stop
```

### Webserver example

First create a web server, `server.php`. This does not need to be in a `public_html` directory or anywhere that is web-accessible:


```php
require 'vendor/autoload.php';
class MyApplication implements \Aphplication\Aphplication {
	//Application state. This will be kept in memory when the application is closed
	//This can even be MySQL connections and other resources

	private $num;

	public function accept(): string {
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
require '../Aphplication/Client.php';
$client = new \Aphplication\Client();
echo $client->connect();
```

(Adjust the path to `Aphplication/Client.php` accordingly). You *could* use composer's autoloader for this, however it's not a good idea as composers autoload is a significant overhead for loading a single file. You'll get better perfomance just using `require` to include the supplied client code.

The supplied client code connects to the server, sends it the get/post/etc data from the current request and returns the response. This PHP file **is** run on every request so try to keep it light!

Now if you visit `client.php` in your browser, you'll see the output from the server. In this case it will show a counter because each time the server is connected to, the `$num` variable is incremented by one.


### Now what?

Your server can do *anything a normal PHP script can do*. Once a `require` statement has been proceessd on the server, that file is required and won't be required again until the server is restarted!

### Development

This does make development more difficult as you have to restart the server each time. Future releases will have a development mode that doesn't actually launch the server but allows you to run clients as if it was (like a normal php script where everything is loaded each time).




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
