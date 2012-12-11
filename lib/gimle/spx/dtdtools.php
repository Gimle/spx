<?php
namespace gimle\spx;

class DtdTools
{
	private $comments = array();
	private $entities = array();
	private $elements = array();
	private $attributes = array();

	public function __construct ($filename, $charset = 'utf-8')
	{
		$charset = strtolower($charset);
		$this->file = file_get_contents($filename);

		echo '<pre>';
		$elements = preg_match_all('/<(.*?)>/s', $this->file, $matches);
		foreach ($matches[1] as $value) {
			if ((substr($value, 0, 3) === '!--') && (substr($value, -2, 2) === '--')) {
				$this->comments[] = trim(str_replace("\r\n", "\n", substr($value, 3, -2)));
			}
			elseif (substr($value, 0, 10) === '!ENTITY % ') {
				$split = explode(' ', trim(preg_replace('/\s+/s', ' ', substr($value, 10))), 2);
				$this->entities[$split[0]] = str_replace(' ', '', substr($split[1], 1, -1));
			}
			elseif (substr($value, 0, 9) === '!ELEMENT ') {
				$split = explode(' ', trim(preg_replace('/\s+/s', ' ', substr($value, 9))), 2);
				$this->elements[$split[0]] = str_replace(' ', '', $split[1]);
			}
			elseif (substr($value, 0, 9) === '!ATTLIST ') {
				$split = explode(' ', substr($value, 9), 2);
				$lines = explode("\n", str_replace("\r\n", "\n", $split[1]));

				$data = array();
				foreach ($lines as $line) {
					$line = trim(preg_replace('/\s+/s', ' ', $line));
					$pos = strpos($line, ' ');
					$data[substr($line, 0, $pos)] = substr($line, $pos + 1);
				}

				$this->attributes[trim($split[0])] = $data;
			}
			else {
				trigger_error('Unknown entry in dtd file: ' . $value, E_USER_ERROR);
			}
		}
	}

	public function getElement ($element)
	{
		$element = preg_replace('/%(.*?);/e', '$this->entities["$1"]', $this->elements[$element]);
		return $this->parseElement($element);
	}

	public function getElements ()
	{
		return $this->elements;
	}

	private function parseElement ($element, $level = 1)
	{
		$return = array();
		$return['type'] = '';
		$return['quant'] = '';
		$return['contents'] = array();

		$elem = array();
		$elem['name'] = '';
		$elem['quant'] = '';

		for ($c = 0; $c < strlen($element); $c++) {
			if ($element[$c] === '(') {
				preg_match('/\((([^()]*|(?R))*)\)/', substr($element, $c), $matches);
				if (isset($matches[0])) {
					$l = strlen($matches[0]);
					if ((isset($element[$c + $l])) && (in_array($element[$c + $l], array('*', '?', '+')))) {
						$ret = $this->parseElement(substr($matches[0], 1) . $element[$c + $l], $level + 1);
						$c += $l;
					}
					else {
						$ret = $this->parseElement(substr($matches[0], 1), $level + 1);
 						$c += $l - 1;
					}
					if ($level !== 1) {
						$return['contents'][] = $ret;
					}
					else {
						$return = $ret;
					}
				}
			}
			elseif ($element[$c] === ')') {
				if ($elem['name'] !== '') {
					$return['contents'][] = $elem;
					$elem = array();
					$elem['name'] = '';
					$elem['quant'] = '';
				}
				if ((isset($element[$c + 1])) && (in_array($element[$c + 1], array('*', '?', '+')))) {
					$return['quant'] = $element[$c + 1];
				}
				return $return;
			}
			elseif (in_array($element[$c], array(',', '|'))) {
				if ($elem['name'] !== '') {
					$return['contents'][] = $elem;
					$elem = array();
					$elem['name'] = '';
					$elem['quant'] = '';
				}
				$return['type'] = $element[$c];
			}
			elseif (in_array($element[$c], array('+', '?', '*'))) {
				if ($element[$c - 1] === ')') {
					$return['quant'] = $element[$c];
				}
				else {
					$elem['quant'] = $element[$c];
				}
			}
			else {
				$elem['name'] .= $element[$c];
			}
		}
		return $return;
	}

	private function recursiveSplit ($string, $layer = 0) {
		$return = array();
		preg_match_all('/\((([^()]*|(?R))*)\)/', $string, $matches);

		if (count($matches) > 1) {
			for ($i = 0; $i < count($matches[1]); $i++) {
				if (is_string($matches[1][$i])) {
					if (strlen($matches[1][$i]) > 0) {
						$return[$layer][] = $matches[1][$i];
						$return = \gimle\core\array_merge_recursive_distinct($return, $this->recursiveSplit($matches[1][$i], $layer + 1));
					}
				}
			}
		}
		return $return;
	}
}
