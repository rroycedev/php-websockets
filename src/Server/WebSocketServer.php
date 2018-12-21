<?php
namespace Server;

class WebSocketServer
{
	private $_host = "";
	private $_port = "";
	private $_clients = null;
	private $_connetions = array();

	public function __construct($host, $port) 
	{
		$this->_host = $host;
		$this->_port = $port;
	}

	public function start()
	{
		$null = NULL; //null var
		//Create TCP/IP sream socket

		$socket = new Socket();

		$socket->bind($this->_port);

		$socket->listen();

		$this->_clients = array($socket->socketHandle);

		while (true) {
			$changed = $this->_clients;

			$socket->select($changed);

			//check for new socket

			if (in_array($socket->socketHandle, $changed)) {
				$socket_new = $socket->accept($socket->socketHandle);
				$this->_clients[] = $socket_new; 
		
				$header = $socket->read($socket_new, 1024);
				$rc = $this->_performHandshaking($header, $socket_new); 

				$ip = $socket->getPeerName($socket_new);

				if ($rc->success) {
					echo "Success\n";
                                        $this->_connections[$rc->user] = $socket_new;

					$response = $this->_mask(json_encode(array('type'=>'success', 'name' => 'System', 'color' => "#0000ff", 'message'=> $rc->user . " is now connected" ))); //prepare json data

	                                $this->_sendMessage($response); //notify all users about new connection
				}	
				else {
                                        $response = $this->_mask(json_encode(array('type'=>'failure', 'name' => 'System', 'color' => "#ff0000", 'message'=> $rc->msg))); //prepare json data

                                         $this->_sendPrivateMessage($outgoing_socket, $response);
                               	}

				//make room for new socket
				$found_socket = array_search($socket->socketHandle, $changed);
				unset($changed[$found_socket]);
			}
	
			//loop through all connected sockets
			foreach ($changed as $changed_socket) {	
		
				//check for any incomming data
				while(true)
				{
					$buf = $socket->receive($changed_socket, 1024);
					if (strlen($buf) == 0) 
					{
						break;
					}

					$received_text = $this->_unmask($buf); //unmask data
					$tst_msg = json_decode($received_text, true); //json decode 


					$fromUsername = "Unknown";

					foreach ($this->_connections as $username => $s) {
						if ($s == $changed_socket) {
							$fromUsername = $username;
						}
					}

					switch ($tst_msg["msgtype"]) {
					case 'chat':
						$user_name = $tst_msg['name']; //sender name

						if (array_key_exists($user_name, $this->_connections)) {
							$outgoing_socket = $this->_connections[$user_name];
							$user_message = $tst_msg['message']; //message text
							$user_color = $tst_msg['color']; //color
						}
						else {
							$outgoing_socket = $changed_socket;
							$user_message = "User not logged in: (" . array_keys($this->_connections) . ")";
							$user_color = "#ff0000";
						}

						//prepare data to be sent to client
						$response_text = $this->_mask(json_encode(array('type'=>'usermsg', 'name'=>$fromUsername, 'message'=>$user_message, 'color'=>$user_color)));

						$this->_sendPrivateMessage($outgoing_socket, $response_text); //send data
						break;			
					}

					break 2; //exist this loop
				}

				$buf = $socket->read($changed_socket, 1024, PHP_NORMAL_READ);
				if ($buf === false) { // check disconnected client
					// remove client for $clients array
					$found_socket = array_search($changed_socket, $this->_clients);
					$ip = $socket->getPeerName($changed_socket);
					unset($this->_clients[$found_socket]);
			
					//notify all users about disconnected connection
					$response = $this->_mask(json_encode(array('type'=>'system', 'message'=>$ip.' disconnected')));
					$this->_sendMessage($response);
				}
			}
		}
		
		$socket->close();	
	}

	private function _sendPrivateMessage($s, $msg)
	{
		@socket_write($s, $msg, strlen($msg));

		return true;
	}

	private function _sendMessage($msg, $excludeSocket = null)
	{
		foreach($this->_connections as $username => $s) {
			if (!$excludeSocket || ($excludeSocket && $s != $excludeSocket)) {
				echo "Sending to user $username\n";
				@socket_write($s,$msg,strlen($msg));
			}
		}
		return true;
	}

	private function _unmask($text) {
		$length = ord($text[1]) & 127;
		if($length == 126) {
			$masks = substr($text, 4, 4);
			$data = substr($text, 8);
		}
		elseif($length == 127) {
			$masks = substr($text, 10, 4);
			$data = substr($text, 14);
		}
		else {
			$masks = substr($text, 2, 4);
			$data = substr($text, 6);
		}
		$text = "";
		for ($i = 0; $i < strlen($data); ++$i) {
			$text .= $data[$i] ^ $masks[$i%4];
		}
		return $text;
	}
	//Encode message for transfer to client.
	private function _mask($text)
	{
		$b1 = 0x80 | (0x1 & 0x0f);
		$length = strlen($text);
	
		if($length <= 125)
			$header = pack('CC', $b1, $length);
		elseif($length > 125 && $length < 65536)
			$header = pack('CCn', $b1, 126, $length);
		elseif($length >= 65536)
			$header = pack('CCNN', $b1, 127, $length);
		return $header.$text;
	}
	//handshake new client.
	private function _performHandshaking($receved_header,$client_conn)
	{
		$headers = array();
		$lines = preg_split("/\r\n/", $receved_header);	
		$error = false;	
		$msg = "";
		$username = "";

		foreach($lines as $line)
		{
			$line = chop($line);

			if (substr($line, 0, 4) == "GET ") 
			{
				$url = substr($line, 4);


				$pos = stripos($url, " ");

				$url = substr($url, 0, $pos);

				$pos = stripos($url, "?");
			
				if ($pos !== FALSE) {
					$args = explode("&", substr($url, $pos + 1));

					foreach ($args as $arg) {
						$parts = explode("=", $arg);

						$key = $parts[0];
						$val = $parts[1];

						if ($key == "user") 
						{
							$username = $val;
						}
					}
				}
			}

			if(preg_match('/\A(\S+): (.*)\z/', $line, $matches))
			{
				$headers[$matches[1]] = $matches[2];
			}
		}

		if ($username == "") {
			$error = true;
			$msg = "No username specified";
		}

		$secKey = $headers['Sec-WebSocket-Key'];
		$secAccept = base64_encode(pack('H*', sha1($secKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));

		$secResult = ($error ? "SUCCESS" : "FAILURE");

		//hand shaking header
		$upgrade  = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
		"Upgrade: websocket\r\n" .
		"Connection: Upgrade\r\n" .
		"WebSocket-Origin: $this->_host\r\n" .
		"WebSocket-Location: ws://$this->_host:$this->_port/server.php\r\n".
		"Sec-WebSocket-Accept:$secAccept\r\n" .
		"WebSocket-Result: $secResult\r\n\r\n";

		socket_write($client_conn,$upgrade,strlen($upgrade));

		return (object)array("success" => !$error, "msg" => $msg, "user" => $username);
	}
}
