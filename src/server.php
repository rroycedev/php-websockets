<?php
require_once("Autoloader.php");

use Server\WebSocketServer;

$shortopts = "";
$longopts = array('host::', 'port::');

$options = getopt($shortopts, $longopts);

if (!array_key_exists("host", $options) || !array_key_exists("port", $options)) {
	Syntax($argv[0]);
}

$host = $options["host"];
$port = $options["port"];

$server = new WebSocketServer($host, $port);

$server->start();

function Syntax($progName) {
	die("Syntax: $progName --host=<hostname> --port=<portnumber>\n");
}


