<?php
namespace gimle\spx;

class Zorba
{
	private $xml = false;

	private $template = '';

	public function __construct (array $params = array ())
	{
		$this->xml = $params;

		$this->template = 'let $xml := doc(\'' . $this->xml['path'] . '\')' . "\n" . 'let $xml := $xml%s' . "\n" . 'return $xml';
	}

	public function xpath ($path)
	{
		$ms = InMemoryStore_getInstance();
		$zorba = Zorba_getInstance($ms);
		try {
			$query = sprintf($this->template, $path);
			$r = Zorba_compileQuery($zorba, $query);
		} catch (\Exception $e) {
			echo 'Zorba error: ' . $e->getMessage(), E_USER_ERROR;
		}

		try {
			$result = XQuery_execute($r);
		} catch (\Exception $e) {
			echo 'Zorba error: ' . $e->getMessage(), E_USER_ERROR;
		}

		XQuery_destroy($r);
		Zorba_shutdown($zorba);
		InMemoryStore_shutdown($ms);

		unset($r, $zorba, $ms);

		$return = $this->omitXmlDecl($result);

		return $return;
	}

	private function omitXmlDecl ($xml)
	{
		$xml = trim(preg_replace('/<\?xml(.*?)\?>/', '', $xml));
		return $xml;
	}
}
