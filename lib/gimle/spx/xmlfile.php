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
		throw new \Exception('The ' . get_called_class() . ' class does not support functx.', Connector::E_METHODNOTFOUND);
	}

	public function insertBefore ($insert, $xpath)
	{
		return $this->insert($insert, $xpath, 'before');
	}

	public function insertAfter ($insert, $xpath)
	{
		return $this->insert($insert, $xpath, 'after');
	}

	private function insert ($insert, $xpath, $pos)
	{
		$replaceString = 'gimle-hopefully-safe-xml-replace-string';

		$dom = $this->xmlGet(Xml::DOM);

		$domXpath = new \DOMXPath($dom);
		$parentXpath = $xpath . '/parent::*';
		$last = libxml_use_internal_errors(true);
		$parent = $domXpath->query($parentXpath);
		if (($parent === false)) {
			throw new \Exception('Parent xpath: "' . $parentXpath . '" could not be found.', Connector::E_XPATH);
		}
		$next = $domXpath->query($xpath);
		if ($next === false) {
			throw new \Exception('Invalid expression: "' . $xpath . '".', Connector::E_XPATH);
		}
		libxml_clear_errors();
		libxml_use_internal_errors($last);

		$comment = new \DOMComment($replaceString);
		if ($pos === 'after') {
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

	public function remove ($xpath)
	{
		$dom = $this->xmlGet(Xml::DOM);
		$domXpath = new \DOMXPath($dom);
		$elems = $domXpath->query($xpath);
		foreach ($elems as $elem) {
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

	public function xpath ($path)
	{
		$return = '';
		$last = libxml_use_internal_errors(true);
		$res = (new \DOMXPath($this->xml->get(Xml::DOM)))->query($path);
		libxml_use_internal_errors($last);
		if ($res === false) {
			throw new \Exception('Invalid expression: "' . $path . '".', Connector::E_XPATH);
		}
		if ($res->length !== 0) {
			foreach ($res as $entry) {
				$return .= $this->xml->get(Xml::DOM)->saveXML($entry) . "\n";
			}
		}
		return rtrim($return, "\n");
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
