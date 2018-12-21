<?php

namespace Server;

class Socket 
{
	public $socketHandle = null;

	public function __construct() 
	{
                $this->socketHandle = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
                socket_set_option($this->socketHandle, SOL_SOCKET, SO_REUSEADDR, 1);
	}

	public function bind($port) 
	{
		socket_bind($this->socketHandle, 0, $port);
	}

	public function listen()
	{
		socket_listen($this->socketHandle);
	}

	public function select(&$socket)
	{	
		$null = NULL;

		socket_select($socket, $null, $null, 0, 10);
	}

	public function accept($s)
	{
		return socket_accept($s);
	}

	public function read($s, $maxDataLen, $readType = PHP_BINARY_READ) 
	{
		return @socket_read($s, $maxDataLen);
	}

	public function getPeerName($s)
	{
		socket_getpeername($s, $ip); 

		return $ip;
	}

	public function receive($s, $maxDataLen)
	{
		$len = socket_recv($s, $buf, $maxDataLen, 0);

		if ($len <= 0)
		{
			return "";
		}

		return $buf;
	}

	public function close()
	{
                socket_close($this->socketHandle);
	}
}

