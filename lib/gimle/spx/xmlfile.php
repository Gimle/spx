<?php
namespace gimle\spx;

class XmlFile
{
	private $xml = false;
	private $schema = false;
	private $transaction = false;

	public function __construct ($xml)
	{
		$this->xml = XmlHandler::factory($xml);
	}

	public function beginTransaction ()
	{
		$this->transaction = $this->xml;
	}

	public function commit ()
	{
		$this->xml->update($this->transaction);
		$this->transaction = false;
		return true;
	}

	public function functx ()
	{
		/**
		 * Not available for file. Use another backend for this feature.
		 */
		throw new \Exception('The ' . get_called_class() . ' class does not support functx.', Xml::E_METHODNOTFOUND);
	}

	public function insertBefore ($insert, $xpath)
	{
		return $this->insert($insert, $xpath, 'before');
	}

	public function insertAfter ($insert, $xpath)
	{
		return $this->insert($insert, $xpath, 'after');
	}

	public function getAttributes ($path)
	{
		$path .= '/@*';
		$return = array();
		$last = libxml_use_internal_errors(true);
		$xpath = new \DOMXPath($this->xml->get(Xml::DOM));
		if (!empty($this->registeredNamespaces)) {
			foreach ($this->registeredNamespaces as $namespace) {
				$xpath->registerNamespace($namespace['prefix'], $namespace['namespaceURI']);
			}
		}
		$res = $xpath->query($path);
		libxml_use_internal_errors($last);
		if ($res === false) {
			throw new \Exception('Invalid expression: "' . $path . '".', Xml::E_XPATH);
		}
		if ($res->length !== 0) {
			foreach ($res as $entry) {
				$return[$entry->nodeName] = $entry->nodeValue;
			}
		}
		return $return;
	}

	public function setAttributes ($xpath, $name, $value)
	{
		$dom = $this->xmlGet(Xml::DOM);

		$domXpath = new \DomXPath($dom);
		$last = libxml_use_internal_errors(true);
		$res = $domXpath->query($xpath);
		if ($res === false) {
			throw new \Exception('Invalid expression: "' . $xpath . '".', Xml::E_XPATH);
		}
		libxml_clear_errors();
		libxml_use_internal_errors($last);

		foreach ($res as $item) {
			$item->setAttribute($name, $value);
		}

		$xml = $dom->saveXML();
		$this->xmlUpdate($xml);

		unset($dom, $xp, $res, $xml);

		return true;
	}

	private function insert ($insert, $xpath, $pos)
	{
		$replaceString = 'gimle-hopefully-safe-xml-replace-string';

		$dom = $this->xmlGet(Xml::DOM);

		$domXpath = new \DOMXPath($dom);

		$parentXpath = $xpath . '/parent::*';
		$last = libxml_use_internal_errors(true);
		$parent = $domXpath->query($parentXpath);
		if ($parent === false) {
			throw new \Exception('Parent xpath: "' . $parentXpath . '" could not be found.', Xml::E_XPATH);
		}
		$next = $domXpath->query($xpath);
		if ($next === false) {
			throw new \Exception('Invalid expression: "' . $xpath . '".', Xml::E_XPATH);
		}
		libxml_clear_errors();
		libxml_use_internal_errors($last);

		$comment = new \DOMComment($replaceString);
		if ($parent->length === 0) {
			$parentXpath = substr($xpath, 0, strrpos($xpath, '/'));
			if ($parentXpath !== '') {
				$parent = $domXpath->query($parentXpath);
				if ($parent === false) {
					throw new \Exception('Parent xpath: "' . $parentXpath . '" could not be found.', Xml::E_XPATH);
				}
				$parent->item(0)->appendChild($comment);
			} else {
				if ($pos === 'after') {
					$dom->insertBefore($comment, $next->item(0)->nextSibling);
				} else {
					$dom->insertBefore($comment, $next->item(0));
				}
			}
		} elseif ($pos === 'after') {
			$parent->item(0)->insertBefore($comment, $next->item(0)->nextSibling);
		} else {
			$parent->item(0)->insertBefore($comment, $next->item(0));
		}

		$xml = $dom->saveXML();
		$xml = str_replace('<!--' . $replaceString . '-->', $insert, $xml);

		$this->xmlUpdate($xml);

		unset($dom, $domXpath, $parent, $next, $comment, $xml);

		return true;
	}

	public function remove ($xpath, $removeTrailingWhitespaceTextNode = false)
	{
		$dom = $this->xmlGet(Xml::DOM);
		$domXpath = new \DOMXPath($dom);
		$elems = $domXpath->query($xpath);
		foreach ($elems as $elem) {
			if (($removeTrailingWhitespaceTextNode === true) && ($elem->nextSibling !== null)) {
				if ($elem->nextSibling->nodeType === XML_TEXT_NODE) {
					if (preg_match('/^\s+$/', $elem->nextSibling->nodeValue) > 0) {
						$elem->parentNode->removeChild($elem->nextSibling);
					}
				}
			}
			$elem->parentNode->removeChild($elem);
		}
		$this->xmlUpdate($dom->saveXML());
		unset($dom, $domXpath, $elems, $elem);
		return true;
	}

	public function replace ($newContent, $xpath)
	{
		// $this->insertAfter($newContent, $xpath);
		// $this->remove($xpath . '[1]');
	}

	public function rollback ()
	{
		$this->transaction = false;
		return true;
	}

	public function save ()
	{
		$this->validate();
		/**
		 * Check if lockfile exists or sleep, retry
		 * Create lockfile
		 * Save
		 * Remove lockfile
		 */
	}

	public function setRuleset ($ruleset, $type = 'schema')
	{
		$this->ruleset = XmlHandler::factory($ruleset);
	}

	public function format ()
	{
		// $dom = new \DOMDocument('1.0', mb_internal_encoding());
		// $dom->preserveWhiteSpace = false;
		// $dom->formatOutput = true;
		// $dom->loadXML($xml->xmlGet(XmlHandler::STRING));
		// $result = preg_replace('/^  |\G  /m', "\t", $dom->saveXML());
	}

	public function validate ()
	{
		// $valid = $dom->schemaValidate(System::$config['path']['rulesets'] . $json['metadata']['schema']);
		if ($this->ruleset !== false) {
		}
	}

	public function getPi ($xpath)
	{
	}

	public function registerNamespace ($prefix, $namespaceURI = false) {
		if ($namespaceURI === false) {
			$xml = $this->xml->get(Xml::DOM);
			$namespaceURI = $xml->lookupNamespaceUri($xml->namespaceURI);
		}
		$this->registeredNamespaces[] = array('prefix' => $prefix, 'namespaceURI' => $namespaceURI);
	}

	public function xmlnsClear () {
		$dom = $this->xml->get(Xml::DOM);
		$e = $dom->documentElement;
		$e->removeAttributeNS($e->getAttributeNode('xmlns')->nodeValue, '');
		$this->xmlUpdate($dom->saveXML());
	}

	public function getNextId ($name, $prefix = 'gimle')
	{
		$ids = $this->xpath('//*/@' . $name, 'value');
		$newId = 1;
		$list = array();
		if (!empty($ids)) {
			foreach ($ids as $value) {
				if (substr($value, 0, strlen($prefix)) === $prefix) {
					if (preg_match('/^' . $prefix . '\d+$/', $value)) {
						$list[] = (int)substr($value, strlen($prefix));
					}
				}
			}
		}
		if (!empty($list)) {
			foreach ($list as $value) {
				$newId = max($list) + 1;
			}
		}
		return $newId;
	}

	public function xpath ($path, $mode = 'xml')
	{
		if ($mode === 'xml') {
			$return = '';
		} else {
			$return = array();
		}
		$last = libxml_use_internal_errors(true);
		$xpath = new \DOMXPath($this->xml->get(Xml::DOM));
		if (!empty($this->registeredNamespaces)) {
			foreach ($this->registeredNamespaces as $namespace) {
				$xpath->registerNamespace($namespace['prefix'], $namespace['namespaceURI']);
			}
		}
		if ((substr($path, 0, 5) === 'name(') && (substr($path, -1, 1) === ')')) {
			$mode = 'name';
			$path = substr($path, 5, -1);
		}
		$res = $xpath->query($path);
		libxml_use_internal_errors($last);
		if ($res === false) {
			throw new \Exception('Invalid expression: "' . $path . '".', Xml::E_XPATH);
		}
		if ($res->length !== 0) {
			foreach ($res as $entry) {
				if ($mode === 'xml') {
					$return .= $this->xml->get(Xml::DOM)->saveXML($entry) . "\n";
				} elseif ($mode === 'value') {
					$return[] = $entry->nodeValue;
				} elseif ($mode === 'name') {
					$return[] = $entry->nodeName;
				} else {
					$return[] = $this->xml->get(Xml::DOM)->saveXML($entry);
				}
			}
		}
		if ($mode === 'xml') {
			return rtrim($return, "\n");
		}
		return $return;
	}

	public function getFormatted ($indent = 'tab')
	{
		$dom = new \DOMDocument('1.0', mb_internal_encoding());
		$dom->preserveWhiteSpace = false;
		$dom->formatOutput = true;
		$dom->loadXML($this->xmlGet(Xml::STRING));
		$return = $dom->saveXML();
		if ($indent === 'tab') {
			$return = preg_replace('/^  |\G  /m', "\t", $return);
		}
		return $return;
	}

	public function xmlGet ($type)
	{
		if ($this->transaction !== false) {
			$dom = $this->transaction->get($type);
		} else {
			$dom = $this->xml->get($type);
		}
		return $dom;
	}

	private function xmlUpdate ($xml)
	{
		if ($this->transaction !== false) {
			$this->transaction->update($xml);
		} else {
			$this->xml->update($xml);
		}
	}
}
