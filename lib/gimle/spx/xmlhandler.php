<?php
namespace gimle\spx;

class XmlHandler implements XmlInterface
{
	private $xml = false;

	public static function factory ($input)
	{
		$name = get_class();
		if ($input instanceof $name) {
			return $input;
		}
		return new $name($input);
	}

	public function get ($type = self::STRING)
	{
		if ($this->xml === false) {
			return false;
		}

		if ($this->xml[$type] !== false) {
			return $this->xml[$type];
		} elseif ($type === self::STRING) {
			return $this->toXml();
		} elseif ($type === self::SIMPLE) {
			$this->xml[self::SIMPLE] = new \SimpleXMLElement($this->toXml());
			return $this->xml[self::SIMPLE];
		} elseif ($type === self::WRITER) {
			$xml = $this->toXml();
			$this->xml[self::WRITER] = new \XMLWriter();
			$this->xml[self::WRITER]->openMemory();
			$this->xml[self::WRITER]->writeRaw($xml);
			return $this->xml[self::WRITER];
		} elseif ($type === self::DOM) {
			$xml = $this->toXml();
			$this->xml[self::DOM] = new \DOMDocument('1.0', mb_internal_encoding());
			$this->xml[self::DOM]->substituteEntities = false;
			$this->xml[self::DOM]->loadXML($xml);
			return $this->xml[self::DOM];
		}

		return false;
	}

	public function update ($input)
	{
		$xml = array(
			self::STRING => false,
			self::SIMPLE => false,
			self::WRITER => false,
			self::DOM => false,
			self::FILE => false,
		);

		if (is_object($input)) {
			if ($input instanceof \SimpleXMLElement) {
				$xml[self::SIMPLE] = $input;
			} elseif ($input instanceof \XMLWriter) {
				$xml[self::WRITER] = $input;
			} elseif ($input instanceof \DOMDocument) {
				$xml[self::DOM] = $input;
			} else {
				throw new \Exception('Not sure what to do with class type: ' . get_class($input), self::E_INPUT);
			}
		} elseif (substr($input, 0, 1) === '<') {
			$xml[self::STRING] = $input;
		} elseif (file_exists($input)) {
			$xml[self::FILE] = $input;
		} else {
			throw new \Exception('Input type not detected.', self::E_INPUT);
		}

		$this->xml = $xml;

		return true;
	}

	private function __construct ($input)
	{
		$this->update($input);
	}

	private function toXml ()
	{
		if ($this->xml[self::STRING] !== false) {
			return $this->xml[self::STRING];
		}

		foreach ($this->xml as $type => $value) {
			if ($this->xml[$type] === false) {
				continue;
			}

			if ($type === self::FILE) {
				$this->xml[self::STRING] = file_get_contents($this->xml[self::FILE]);
				return $this->xml[self::STRING];
			} elseif ($type === self::SIMPLE) {
				$this->xml[self::STRING] = $this->xml[self::SIMPLE]->asXML();
				return $this->xml[self::STRING];
			} elseif ($type === self::WRITER) {
				$this->xml[self::STRING] = $this->xml[self::WRITER]->outputMemory();
				return $this->xml[self::STRING];
			} elseif ($type === self::DOM) {
				$this->xml[self::STRING] = $this->xml[self::DOM]->saveXML();
				return $this->xml[self::STRING];
			} else {
				continue;
			}

			break;
		}
		return false;
	}
}
