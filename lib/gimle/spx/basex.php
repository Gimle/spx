<?php
namespace gimle\spx;

class BaseX
{
	private $socket;
	private $info;
	private $buffer;
	private $bpos;
	private $bsize;

	private $status = true;

	public function __construct (array $params = array ()) {
		$params['pass'] = (isset($params['pass']) ? $params['pass'] : '');
		$params['user'] = (isset($params['user']) ? $params['user'] : 'admin');
		$params['host'] = (isset($params['host']) ? $params['host'] : '127.0.0.1');
		$params['port'] = (isset($params['port']) ? $params['port'] : 1984);
		$params['timeout'] = (isset($params['timeout']) ? $params['timeout'] : 1.0501234);
		$params['database'] = (isset($params['database']) ? $params['database'] : false);

		$this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);

		socket_set_block($this->socket);

		$this->setTimeout($params['timeout']);

		if (!@socket_connect($this->socket, $params['host'], $params['port'])) {
			$sock = array($this->socket);
			$res = socket_select($sock, $sock, $sock, 1);
			if ($res === 2) {
				throw new \Exception('Connection refused.', Connector::E_READ);
			}
			elseif ($res === 0) {
				throw new \Exception('Connection timeout.', Connector::E_TIMEOUT);
			}
			throw new \Exception('Unknown connection error.', Connector::E_UNKNOWN);
		}

		$timestamp = $this->readString();
		$md5 = hash('md5', hash('md5', $params['pass']) . $timestamp);
		socket_write($this->socket, $params['user'] . chr(0) . $md5 . chr(0));

		if (socket_read($this->socket, 1) !== chr(0)) {
			throw new \Exception('No access.', Connector::E_READ);
			return false;
		}

		if ($params['database'] !== false) {
			$res = $this->execute('OPEN ' . $params['database']);
		}

		return true;
	}

	public function xpath ($path)
	{
		$this->execute('declare option db:chop \'false\'');
		return $this->execute('XQUERY ' . $path);
	}

	private function execute ($com) {
		if ($this->status === true) {
			socket_write($this->socket, $com. chr(0));

			$result = $this->receive();
			$this->info = $this->readString();
			return $result;
		}
		return false;
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

	private function readString () {
		if ($this->status === true) {
			$com = '';
			while (($d = $this->read()) !== chr(0)) {
				$com .= $d;
			}
			return $com;
		}
		return false;
	}

	private function receive () {
		$this->init();
		return $this->readString();
	}

	private function setTimeout ($time) {
		$time = str_replace(',', '.', (string)$time);
		ini_set('default_socket_timeout', $time);

		$time = explode('.', $time);
		if (!isset($time[1])) {
			$time[1] = '0';
		}
		$time[0] = (int)$time[0];
		$time[1] = (int)substr(str_pad($time[1], 6, '0', STR_PAD_RIGHT), 0, 6);

		socket_set_option($this->socket, SOL_SOCKET, SO_RCVTIMEO, array('sec' => $time[0], 'usec' => $time[1]));
		socket_set_option($this->socket, SOL_SOCKET, SO_SNDTIMEO, array('sec' => $time[0], 'usec' => $time[1]));
	}
}
