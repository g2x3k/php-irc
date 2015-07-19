<?php
define('HTTP_OK', "200 OK");
define('HTTP_BAD_REQUEST', "400 Bad Request");
define('HTTP_NOT_FOUND', "404 Not Found");
define('HTTP_AUTH', "401 Unauthorized");

class http_request {
	public $command;
	public $target;
	public $get = array();
	public $post = array();
	public $cookie = array();
	public $headers = array();
	public $postData = "";
	public $postLength = 0;
	public $writeHeaders = "";
}

class http_session {
	public $connection;
	public $sockInt;
	public $requestEnded;
	public $finished;
	public $time;
	public $queue;
	public $writeQueue = "";
	public $request = null;
}
?>
