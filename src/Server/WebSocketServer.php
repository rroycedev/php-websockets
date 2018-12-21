<?php
namespace Server;

class WebSocketServer
{
	private $_host = "";
	private $_port = "";
	private $_clients = null;

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
				$this->_performHandshaking($header, $socket_new); 
		
				$ip = $socket->getPeerName($socket_new);

				$response = $this->_mask(json_encode(array('type'=>'system', 'message'=>$ip.' connected'))); //prepare json data
				$this->_sendMessage($response); //notify all users about new connection
		
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

					echo "Message:\n";
					print_r($tst_msg);

					$user_name = $tst_msg['name']; //sender name
					$user_message = $tst_msg['message']; //message text
					$user_color = $tst_msg['color']; //color
				
					//prepare data to be sent to client
					$response_text = $this->_mask(json_encode(array('type'=>'usermsg', 'name'=>$user_name, 'message'=>"Received: " . $user_message, 'color'=>$user_color)));
					$this->_sendMessage($response_text); //send data
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

	private function _sendMessage($msg)
	{
		foreach($this->_clients as $changed_socket)
		{
			@socket_write($changed_socket,$msg,strlen($msg));
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
		foreach($lines as $line)
		{
			$line = chop($line);
			if(preg_match('/\A(\S+): (.*)\z/', $line, $matches))
			{
				$headers[$matches[1]] = $matches[2];
			}
		}

		$secKey = $headers['Sec-WebSocket-Key'];
		$secAccept = base64_encode(pack('H*', sha1($secKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11')));
		//hand shaking header
		$upgrade  = "HTTP/1.1 101 Web Socket Protocol Handshake\r\n" .
		"Upgrade: websocket\r\n" .
		"Connection: Upgrade\r\n" .
		"WebSocket-Origin: $this->_host\r\n" .
		"WebSocket-Location: ws://$this->_host:$this->_port/server.php\r\n".
		"Sec-WebSocket-Accept:$secAccept\r\n\r\n";

		socket_write($client_conn,$upgrade,strlen($upgrade));
	}
}



