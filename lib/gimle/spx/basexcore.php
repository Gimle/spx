<?php
namespace gimle\spx;

class BaseXCore {
	private $socket;
	private $info;
	private $buffer;
	private $bpos;
	private $bsize;

	private $status = true;

	function __construct (array $params = array ()) {
		$params['pass'] = (isset($params['pass']) ? $params['pass'] : '');
		$params['user'] = (isset($params['user']) ? $params['user'] : 'root');
		$params['host'] = (isset($params['host']) ? $params['host'] : '127.0.0.1');
		$params['port'] = (isset($params['port']) ? $params['port'] : 1984);
		$params['database'] = (isset($params['database']) ? $params['database'] : false);

		$this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

		socket_set_block($this->socket);

		$this->setTimeout(1.0501234);
		ini_set('default_socket_timeout', 2);

		if (!socket_connect($this->socket, $params['host'], $params['port'])) {
			$this->status = 'No connection.';
			$sock = array($this->socket);
			$res = socket_select($sock, $sock, $sock, 1);
			if ($res === 2) {
				$this->status = 'Connection refused.';
			}
			elseif ($res === 0) {
				$this->status = 'Timeout.';
			}
			else {
				$this->status = 'Unknown connection error.';
			}
			return false;
		}

		$timestamp = $this->readString();
		$md5 = hash('md5', hash('md5', $params['pass']) . $timestamp);
		socket_write($this->socket, $params['user'] . chr(0) . $md5 . chr(0));

		if (socket_read($this->socket, 1) !== chr(0)) {
			$this->status = 'No access';
			return false;
		}

		if ($params['database'] !== false) {
			$res = $this->execute('OPEN ' . $params['database']);
		}

		return true;
	}

	public function setTimeout ($time) {
		$time = explode('.', str_replace(',', '.', (string)$time));
		if (!isset($time[1])) {
			$time[1] = '0';
		}
		$time[0] = (int)$time[0];
		$time[1] = (int)substr(str_pad($time[1], 6, '0', STR_PAD_RIGHT), 0, 6);

		socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, array('sec' => $time[0], 'usec' => $time[1]));
		socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO, array('sec' => $time[0], 'usec' => $time[1]));
	}

	public function readString () {
		if ($this->status === true) {
			$com = '';
			while (($d = $this->read()) !== chr(0)) {
				$com .= $d;
			}
			return $com;
		}
		return false;
	}

	public function execute ($com) {
		if ($this->status === true) {
			socket_write($this->socket, $com. chr(0));

			$result = $this->receive();
			$this->info = $this->readString();
			return $result;
		}
		return false;
	}

	public function receive () {
		$this->init();
		return $this->readString();
	}

	private function init () {
		$this->bpos = 0;
		$this->bsize = 0;
	}

	private function read () {
		if ($this->bpos === $this->bsize) {
			$this->bsize = socket_recv($this->socket, $this->buffer, 4096, 0);
			$this->bpos = 0;
		}
		return $this->buffer[$this->bpos++];
	}
}
