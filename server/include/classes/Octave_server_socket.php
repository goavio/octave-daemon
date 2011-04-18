<?php

/**
* Octave-daemon -- a network daemon for Octave, written in PHP
*
* Copyright (C) 2011 Bogdan Stancescu <bogdan@moongate.ro>
*
* This program is free software: you can redistribute it and/or modify
* it under the terms of the GNU Affero General Public License as
* published by the Free Software Foundation, either version 3 of the
* License, or (at your option) any later version.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU Affero General Public License for more details.
*
* You should have received a copy of the GNU Affero General Public License
* along with this program.  If not, see {@link http://www.gnu.org/licenses/}.
*
* @package octave-daemon-server
* @author Bogdan Stăncescu <bogdan@moongate.ro>
* @version 1.0
* @copyright Copyright (c) 2011, Bogdan Stăncescu
* @license http://www.gnu.org/licenses/agpl-3.0.html GNU Affero GPL
*/

/**
* This class manages server socket connections.
*
* @package octave-daemon-server
*/
class Octave_server_socket
{
	public $server_address='127.0.0.1';
	public $server_port=43210;
	public $lastError="";
	public $allowedIP=array('127.0.0.1');
	public $msgSep="\r\n";
	public $hangingServer=false;

	private $lockptr=NULL;
	private $initialized=false;
	private $socket=NULL;

	public function __construct()
	{
	}

	public function init()
	{
		if ($this->initialized)
			return NULL;

		if (!$this->lock()) {
			Octave_logger::getCurrent()->log("This server is already running.",LOG_INFO);
			return false;
		}

		$this->socket=@socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
		if ($this->socket===false) {
			$this->setError($this->getSocketError(
				"Failed creating socket"
			),LOG_ERR);
			$this->unlock();
			return false;
		}

		if (!@socket_bind($this->socket,$this->server_address,$this->server_port)) {
			$this->setError($this->getSocketError(
				"Failed binding socket to ".
				$this->server_address.":".$this->server_port
			),LOG_ERR);
			$this->unlock();
			return false;
		}

		if (!@socket_listen($this->socket, 5)) {
			$this->setError($this->getSocketError(
				"Failed starting to listen on socket"
			),LOG_ERR);
			$this->unlock();
			return false;
		}

		socket_set_nonblock($this->socket);

		return $this->initialized=true;
	}

	public function __destruct()
	{
		if (!$this->initialized || $this->hangingServer)
			return;

		echo "Server being destroyed in ".getmypid()."\n";

		//socket_shutdown($this->socket);
		socket_close($this->socket);
	}

	public function accept_connection()
	{
		$cSocket=@socket_accept($this->socket);

		// We're running non-blocking, so this just means that
		// nobody's knocking
		if ($cSocket===false)
			return NULL;

		if ($cSocket<0) {
			$this->setError($this->getSocketError(
				"Failed accepting connection"
			),LOG_ERR);
			socket_close($cSocket);
			return false;
		}

		if (!@socket_getpeername($cSocket,$remote_IP)) {
			$this->setError($this->getSocketError(
				"Failed retrieving the remote IP address"
			),LOG_WARNING);
			socket_close($cSocket);
			return false;
		}

		// IP filtering, if enabled
		if ($this->allowedIP && !in_array($remote_IP,$this->allowedIP)) {
			Octave_logger::getCurrent()->log("Connection attempt from ".$remote_IP);
			socket_close($cSocket);
			return NULL;
		}

		return $cSocket;
	}

	public function close()
	{
		socket_close($this->socket);
	}

	protected function getSocketError($msg)
	{
		$errorcode = socket_last_error();
		$errormsg = socket_strerror($errorcode);

		return $msg." [".$errorcode."]: ".$errormsg;
	}

	protected function setError($message,$priority=LOG_WARNING)
	{
		Octave_logger::getCurrent()->log($message,$priority);
		$this->lastError=$message;
	}

	private function lock()
	{
		$this->lockptr=fopen(__FILE__,'r');
		if (flock($this->lockptr,LOCK_EX+LOCK_NB))
			return true;

		fclose($this->lockptr);
		return false;
	}

	private function unlock()
	{		
		if (!flock($this->lockptr,LOCK_UN))
			return false;

		fclose($this->lockptr);
		return true;
	}

}
