<?php
namespace gimle\spx;

class Schema {
	protected $schema = false;

	protected $cache = array();

	public function __construct ($schema)
	{
		$this->schema = Xml::load($schema);
		$this->schema->xmlGet(Xml::SIMPLE)->registerXPathNamespace('xs', 'http://www.w3.org/2001/XMLSchema');
	}

	public function getElementNames ()
	{
		$return = array();

		$result = $this->schema->xmlGet(Xml::SIMPLE)->xpath('//xs:element/@name');

		foreach ($result as $value) {
			$return[(string)$value['name']] = (string)$value['name'];
		}
		return $return;
	}

	// public function getAttributes ($element = false)
	// {
	// 	$return = array();
	// 	$result = $this->schema->get(Xml::SIMPLE)->xpath('//xs:element/*');
	// 	foreach ($result as $value) {
	// 		$name = (string)$value->xpath('parent::*')[0]->attributes()['name'];
	// 		$return[$name] = array();
	// 		$children = $value->xpath('child::xs:attribute');
	// 		if (!empty($children)) {
	// 			foreach ($children as $subValue) {
	// 				$attribute = (string)$subValue->attributes()['name'];
	// 				$return[$name][$attribute] = array();
	// 				$one = $subValue->xpath('child::*');
	// 				if (!empty($one)) {
	// 					foreach ($subValue->xpath('child::*')[0]->xpath('child::*')[0]->xpath('child::*') as $subSubValue) {
	// 						$return[$name][$attribute][(string)$subSubValue->attributes()['value']] = (string)$subSubValue->attributes()['value'];
	// 					}
	// 				}
	// 			}
	// 		}
	// 	}

	// 	if ($element !== false) {
	// 		$return = $return[$element];
	// 	}

	// 	return $return;
	// }

	public function getAttributes ($element = false)
	{
		if (isset($this->cache['getAttributes'])) {
			$return = $this->cache['getAttributes'];
		} else {
			$return = array();
			$result = $this->schema->xmlGet(Xml::SIMPLE)->xpath('//xs:element/*');
			foreach ($result as $value) {
				$name = (string)$value->xpath('parent::*')[0]->attributes()['name'];
				$return[$name] = array();
				$return[$name]['name'] = $name;
				$return[$name]['attr'] = array();
				$type = false;
				if ($value->xpath('parent::*')[0]->attributes()['type'] !== null) {
					$type = substr($value->xpath('parent::*')[0]->attributes()['type'], 3);
					$children = $value->xpath('//*[@name="' . $type . '"]')[0]->xpath('child::xs:attribute|*//xs:attribute');
					$test = $value->xpath('//*[@name="' . $type . '"]')[0]->xpath('child::xs:anyAttribute|*//xs:anyAttribute');
					if (!empty($test)) {
						$return[$name]['attr-wild'] = true;
					}
				} else {
					$children = $value->xpath('child::xs:attribute|*//xs:attribute');
				}
				if (!empty($children)) {
					foreach ($children as $subValue) {
						$attribute = (string)$subValue->attributes()['name'];
						$return[$name]['attr'][$attribute] = array();
						$return[$name]['attr'][$attribute]['name'] = $attribute;
						$return[$name]['attr'][$attribute]['use'] = (string)$subValue->attributes()['use'];
						$return[$name]['attr'][$attribute]['type'] = (string)$subValue->attributes()['type'];
						$return[$name]['attr'][$attribute]['values'] = array();
						$one = $subValue->xpath('child::*');
						if (!empty($one)) {
							foreach ($subValue->xpath('child::*')[0]->xpath('child::*')[0]->xpath('child::*') as $subSubValue) {
								$return[$name]['attr'][$attribute]['values'][(string)$subSubValue->attributes()['value']] = (string)$subSubValue->attributes()['value'];
							}
						}
					}
				}
			}
			$this->cache['getAttributes'] = $return;
		}

		if ($element !== false) {
			if (strpos($element, ':') !== false) {
				$element = substr($element, strpos($element, ':') + 1);
			}
			if (isset($return[$element])) {
				$return = $return[$element];
			} else {
				return array();
			}
		}

		return $return;
	}

	public function canHaveCdata ($element) {
		if (in_array('#CDATA', $this->getValidChildren($element))) {
			return true;
		}
		return false;
	}

	public function getAttributesDraft ($element) {
		$return = array();
		$elem = $this->schema->xmlGet(Xml::SIMPLE)->xpath('//xs:element[@name="' . $element . '"]');
		if (empty($elem)) {
			return $return;
		}

		$elements = $this->schema->xmlGet(Xml::SIMPLE)->xpath('//xs:element[@name="' . $element . '"]/*/*[not(self::xs:attribute)]');
		if (!empty($elements)) {
			foreach ($elements as $child) {
				if ($child->getName() === 'sequence') {
					$return[] = $this->getSequence($child);
				} else {
					throw new \Exception('Element not known: ' . $child->getName());
				}
			}
		}

		\gimle\common\var_dump($return);

		return $this->getValidChildren($element);
	}

	private function getSequence ($element) {
		$return = array();
		foreach ($element->xpath('*') as $child) {
			if ($child->getName() === 'element') {
				$return[] = array('xml' => $child->asXML());
			} elseif ($child->getName() === 'choice') {
				foreach ($child->xpath('*') as $choice) {
					if ($choice->getName() === 'group') {
						$return[] = $this->getGroup($choice);
					} else {
						throw new \Exception('Element not known: ' . $choice->getName());
					}
				}
			} else {
				throw new \Exception('Element not known: ' . $child->getName());
			}
		}
		return $return;
	}

	private function getGroup ($element) {
		$elements = $this->schema->xmlGet(Xml::SIMPLE)->xpath('//xs:group[@name="' . (string)$element->attributes()['ref'] . '"]');
		\gimle\common\var_dump($elements);
	}

	public function getRequiredXml ($file, $xpath, $element)
	{
		$attr = $this->getAttributes($element);
		$xml = '<' . $element;
		if (!empty($attr)) {
			$attr = $attr['attr'];
			if (!empty($attr)) {
				foreach ($attr as $value) {
					if ($value['use'] === 'required') {
						$xml .= ' ' . $value['name'] . '="';
						if ($value['type'] === 'xs:ID') {
							$xml .= \gimle\common\generate_password();
						}
						$xml .= '"';
					}
				}
			}
		}
		$children = $this->getRequiredChildren($element);
		if (!empty($children)) {
			$xml .= '>';
			foreach ($children as $child) {
				$xml .= $this->getRequiredXml($file, $xpath, $child);
			}
			$xml .= '</' . $element . '>';
		} else {
			$xml .= '/>';
		}

		return $xml;
	}

	// private function makeUid ($file) {
	// 	$checker = Xml::factory($file)->get(Xml::SIMPLE)->xpath('//*[@id]');
	// 	$res = array();
	// 	foreach ($checker as $child) {
	// 		$res[] = (string)$child->attributes()['id'];
	// 	}
	// 	$s = \gimle\common\generate_password();
	// 	if ()
	// }

	public function getRequiredChildren ($element) {
		$return = array();

		$elem = $this->schema->xmlGet(Xml::SIMPLE)->xpath('//xs:element[@name="' . $element . '"]/*');
		if (empty($elem)) {
			return $return;
		}
		$elements = $this->schema->xmlGet(Xml::SIMPLE)->xpath('//xs:element[@name="' . $element . '"]/*/*/xs:element|//xs:element[@name="' . $element . '"]/*/*/xs:group|//xs:element[@name="' . $element . '"]/*/*/xs:choice');
		if (!empty($elements)) {
			$mode = $elements[0]->xpath('parent::*')[0]->getName();
			foreach ($elements as $element) {
				if ($mode === 'sequence') {
					if ($element->getName() === 'element') {
						$howmany = $element->attributes()->minOccurs;
						if (($howmany === null) || ((int)$howmany > 0)) {
							$return[(string)$element->attributes()->ref] = (string)$element->attributes()->ref;
						}
					} elseif ($element->getName() === 'choice') {
						$howmany = $element->attributes()->minOccurs;
						if (($howmany === null) || ((int)$howmany > 0)) {
							foreach ($element->xpath('*') as $choice) {
								$return[(string)$choice->attributes()->ref] = (string)$choice->attributes()->ref;
							}
						}
					}
				}
			}
		}

		return $return;
	}

	public function getValidChildren ($element) {
		$return = array();
		$elem = $this->schema->xmlGet(Xml::SIMPLE)->xpath('//xs:element[@name="' . $element . '"]/*');
		if (empty($elem)) {
			return $return;
		}
		$mixedAttr = (string)$elem[0]->attributes()->mixed;
		if ($mixedAttr === 'true') {
			$mixed = true;
			$return['#CDATA'] = '#CDATA';
		} else {
			$mixed = false;
		}
		$elements = $this->schema->xmlGet(Xml::SIMPLE)->xpath('//xs:element[@name="' . $element . '"]//xs:element');
		if (!empty($elements)) {
			foreach ($elements as $subElement) {
				$name = (string)$subElement->attributes()->ref;
				if ($name !== '') {
					$return[$name] = $name;
				}
			}
		}
		$groups = $this->schema->xmlGet(Xml::SIMPLE)->xpath('//xs:element[@name="' . $element . '"]//xs:group');
		if (!empty($groups)) {
			foreach ($groups as $group) {
				$name = (string)$group->attributes()->ref;
				if ($name !== '') {
					$elements = $this->schema->xmlGet(Xml::SIMPLE)->xpath('//xs:group[@name="' . $name . '"]//xs:element');
					if (!empty($elements)) {
						foreach ($elements as $subElement) {
							$name = (string)$subElement->attributes()->ref;
							if ($name !== '') {
								$return[$name] = $name;
							}
						}
					}
				}
			}
		}
		$extensions = $this->schema->xmlGet(Xml::SIMPLE)->xpath('//xs:element[@name="' . $element . '"]//xs:extension');
		if (!empty($extensions)) {
			foreach ($extensions as $extension) {
				$name = (string)$extension->attributes()->base;
				if ($name !== '') {
					$elem = $this->schema->xmlGet(Xml::SIMPLE)->xpath('/xs:schema/xs:complexType[@name="' . $name . '"]')[0];
					$mixedAttr = (string)$elem->attributes()->mixed;
					if ($mixedAttr === 'true') {
						$mixed = true;
						$return['#CDATA'] = '#CDATA';
					} else {
						$mixed = false;
					}
					$elements = $this->schema->xmlGet(Xml::SIMPLE)->xpath('/xs:schema/xs:complexType[@name="' . $name . '"]//xs:element');
					if (!empty($elements)) {
						foreach ($elements as $subElement) {
							$name = (string)$subElement->attributes()->ref;
							if ($name !== '') {
								$return[$name] = $name;
							}
						}
					}
				}
			}
		}
		return $return;
	}
}
