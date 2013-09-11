<?php
namespace gimle\spx;

class SchemaTranslator extends Schema
{
	private $translation = false;

	private $cahce = array();

	public function __construct ($schema, $translation = false)
	{
		if ($translation !== false) {
			$this->translation = XmlHandler::factory($translation);
		}
		parent::__construct($schema);
	}

	/**
	 * Retrieve an array with the translations of elements.
	 *
	 * @todo Store this nice xpath somewhere for later usage: $result = $this->translation->get(Xml::SIMPLE)->xpath('//element/' . $language . '[not(@name=preceding::' . $language . '/@name)]/@name');
	 *
	 * @param string $language
	 * @return boolean|array
	 */
	public function getElementTranslations ($language, $reverse = false)
	{
		if ($this->translation === false) {
			return false;
		}

		$return = array();
		$result = $this->translation->get(Xml::SIMPLE)->xpath('//element/' . $language . '/@name');

		$translations = array();
		foreach ($result as $value) {
			$translations[(string)$value['name']] = (string)$value[0]->xpath('parent::*')[0]->xpath('parent::*')[0]->attributes()['name'];
		}
		$return['missing'] = array();
		$return['overflow'] = array();
		$return['found'] = array();
		// d($translations); // $this->getElementNames() system -> system | $translations lang -> system
		foreach ($this->getElementNames() as $elem) {
			if ((($reverse === false) && (!isset($translations[$elem]))) || ($reverse === true)) {
				$test = (array)$this->translation->get(Xml::SIMPLE)->xpath('//element[@name=\'' . $elem . '\']');
				if ((!empty($test)) && (isset($test[0]->children()->$language))) {
					if ($reverse === true) {
						foreach ($translations as $ret => $val) {
							if ($val === $elem) {
								$return['found'][$ret] = $elem;
								break;
							}
						}
					} else {
						$return['found'][$elem] = $elem;
					}
				} else {
					$return['missing'][$elem] = $elem;
				}
			} else {
				$return['found'][$elem] = $translations[$elem];
			}
		}
		foreach ($translations as $from => $to) {
			if (!isset($return['found'][$from])) {
				$return['overflow'][$from] = $to;
			}
		}

		if ($reverse === true) {
			foreach ($return as $key => $value) {
				$return[$key] = array_flip($value);
			}
		}

		return $return;
	}

	public function getElementTranslation ($language, $element, $reverse = false)
	{
		if ($this->translation === false) {
			return false;
		}

		$return = $this->getElementTranslations($language, $reverse);
		if (isset($return['found'][$element])) {
			return $return['found'][$element];
		}

		return false;
	}

	public function getAttributeTranslation ($language, $element, $reverse = false)
	{
		if ($this->translation === false) {
			return false;
		}
		$result = $this->getAttributeTranslations($language, $reverse);
		$return = $result['found']['*'];
		if (isset($result['found'][$element])) {
			$return = \gimle\core\array_merge_recursive_distinct($return, $result['found'][$element]);
		}

		return $return;
	}

	/**
	 * Retrieve an array with the translations of attributes and their values.
	 *
	 * @todo Store this nice xpath somewhere for later usage: $result = $this->translation->get(Xml::SIMPLE)->xpath('//attribute/' . $language . '[not(@name=preceding::' . $language . '/@name)]/@name');
	 *
	 * @param string $language
	 * @return false|array
	 */
	public function getAttributeTranslations ($language, $reverse = false)
	{
		if ($this->translation === false) {
			return false;
		}

		$elems = $this->getElementTranslations($language);
		if (!empty($elems['missing'])) {
			return false;
		}

		$return = array();
		$return['missing'] = array();
		$return['found'] = array();

		$result = $this->translation->get(Xml::SIMPLE)->xpath('//attribute/' . $language);
		$translations = array();
		$translations['*'] = array();
		foreach ($result as $value) {
			$tname = (string)$value->attributes()['name'];
			$element = (string)$value[0]->xpath('parent::*')[0]->attributes()['element'];
			$name = (string)$value[0]->xpath('parent::*')[0]->attributes()['name'];
			if ($tname === '') {
				$tname = $name;
			}
			$translations[$element][$tname]['name'] = $name;
			$parents = $value->xpath('parent::*')[0]->values->value;
			if (!is_null($parents)) {
				foreach ($value->xpath('parent::*')[0]->values->value as $subValue) {
					if (isset($subValue->$language)) {
						if ($subValue->$language->attributes()) {
							$translations[$element][$tname]['values'][(string)$subValue->$language->attributes()['name']] = (string)$subValue->attributes()['name'];
						} else {
							$translations[$element][$tname]['values'][(string)$subValue->attributes()['name']] = (string)$subValue->attributes()['name'];
						}
					}
				}
			}
		}

		foreach ($translations as $element => $value) {
			if ($element === '*') {
				continue;
			}
			foreach ($value as $attr => $keys) {
				if (isset($translations['*'][$attr]['values'])) {
					if  (isset($keys['values'])) {
						$translations[$element][$attr]['values'] = array_merge($translations['*'][$attr]['values'], $translations[$element][$attr]['values']);
					} else {
						$translations[$element][$attr]['values'] = $translations['*'][$attr]['values'];
					}
				}
			}
		}

		if ($reverse === true) {
			$org = $translations;
			$translations = array();
			foreach ($org as $section => $values) {
				foreach ($values as $key => $value) {
					$translations[$section][$value['name']] = $value;
					$translations[$section][$value['name']]['name'] = $key;
					if (!empty($translations[$section][$value['name']]['values'])) {
						$bak = $translations[$section][$value['name']]['values'];
						$translations[$section][$value['name']]['values'] = array();
						foreach ($bak as $subKey => $subValue) {
							$translations[$section][$value['name']]['values'][$subValue] = $subKey;
						}
					}
				}
			}
		}

		$return['found'] = $translations;
		foreach ($this->getAttributes() as $element => $attrs) {
			if (empty($attrs['attr'])) {
				continue;
			}
			$check = $elems['found'][$element];
			if ($check === '') {
				$check = $element;
			}

			$ok = true;
			$missingAttrs = array();
			foreach ($attrs['attr'] as $name => $attr) {
				if ((!isset($translations['*'][$name])) && (!isset($translations[$check][$name]))) {
					$ok = false;
					$missingAttrs[$name] = array();
				}
				foreach ($attr['values'] as $key => $value) {
					if ((!isset($translations['*'][$name]['values'][$key])) && (!isset($translations[$check][$name]['values'][$key]))) {
						// echo 1;
						$ok = false;
						$missingAttrs[$name][$key] = $key;
					}
				}
			}
			if ($ok === true) {
				/* These are mostly * matches, and is not important */
			} else {
				if ($elems['found'][$element] !== '') {
					$ttitle = $elems['found'][$element];
				} else {
					$ttitle = $element;
				}
				$return['missing'][$ttitle] = $missingAttrs;
			}
		}

		return $return;
	}

	/**
	 * Translate the loaded xsl from a locale language to system english.
	 *
	 * @param string $language
	 * @return false|string
	 */
	public function xslTranslate ($language)
	{
		if ($this->translation === false) {
			return false;
		}

		$translations = $this->getElementTranslations($language);
		if ((empty($translations['found'])) || (!empty($translations['missing']))) {
			return false;
		}

		$xml = Xml::load($this->mergeXsl($this->genTemplate('elements', $translations)));

		$translations = $this->getAttributeTranslations($language);
		if ((empty($translations['found'])) || (!empty($translations['missing']))) {
			return false;
		}

		$xml = Xml::load($this->mergeXsl($this->genTemplate('attributes', $translations), $xml));
		$xml = Xml::load($this->mergeXsl($this->genTemplate('value', $translations), $xml));

		return $xml->xmlGet(Xml::STRING);
	}

	/**
	 * This method was splitted out from xslTemplate to be able to retrieve the generated xsl for debugging and optimizing.
	 *
	 * @param string $type
	 * @param array $translations
	 * @return string
	 */
	private function genTemplate ($type, $translations = array())
	{
		$template = '<?xml version="1.0"?>
<xsl:stylesheet version="1.0"
				xmlns="http://www.w3.org/2001/XMLSchema"
				xmlns:xs="http://www.w3.org/2001/XMLSchema"
				xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
>
	<xsl:strip-space elements="*" />
	<xsl:output method="xml" indent="yes" />

	<!-- This template copies everything that doesn\'t have a more specific rule -->
	<xsl:template match="node()|@*">
		<xsl:copy>
			<xsl:apply-templates select="node()|@*" />
		</xsl:copy>
	</xsl:template>

	%s

</xsl:stylesheet>';

		if ($type === 'elements') {
			$xsl = '

	<xsl:template match="xs:element">
		<xsl:element name="xs:element">
			<xsl:choose>';
			foreach ($translations['found'] as $from => $to) {
				$xsl .= '
				<xsl:when test="@name=\'' . $from . '\'">
					<xsl:copy-of select="./@*"/>
					<xsl:attribute name="name">
						<xsl:text>' . $to . '</xsl:text>
					</xsl:attribute>
				</xsl:when>
				<xsl:when test="@ref=\'' . $from . '\'">
					<xsl:copy-of select="./@*"/>
					<xsl:attribute name="ref">
						<xsl:text>' . $to . '</xsl:text>
					</xsl:attribute>
				</xsl:when>';
			}

			$xsl .= '

				<xsl:otherwise>
					<xsl:copy-of select="./@*"/>
				</xsl:otherwise>';

			$xsl .= '
			</xsl:choose>
			<xsl:apply-templates />
		</xsl:element>
	</xsl:template>';
		} elseif ($type === 'attributes') {
			$xsl = '';
			foreach ($translations['found'] as $element => $value) {
				foreach ($value as $tname => $subValue) {
					if (($tname === $subValue['name']) && (!isset($subValue['values']))) {
						continue;
					}
					if ($element === '*') {
						$xsl .= '

	<xsl:template match="xs:element//xs:attribute[@name=\'' . $tname . '\']">
		<xsl:element name="xs:attribute">
			<xsl:copy-of select="./@*"/>
			<xsl:attribute name="name">
				<xsl:text>' . $subValue['name'] . '</xsl:text>
			</xsl:attribute>
			<xsl:apply-templates />
		</xsl:element>
	</xsl:template>';
						if (isset($subValue['values'])) {
							$xsl .= "\n\t" . '<xsl:template match="xs:element//xs:attribute[@name=\'' . $tname . '\']//xs:enumeration">' . "\n";
						}
					} else {
						$xsl .= '

	<xsl:template match="xs:element[@name=\'' . $element . '\']//xs:attribute[@name=\'' . $tname . '\']">
		<xsl:element name="xs:attribute">
			<xsl:copy-of select="./@*"/>
			<xsl:attribute name="name">
				<xsl:text>' . $subValue['name'] . '</xsl:text>
			</xsl:attribute>
			<xsl:apply-templates />
		</xsl:element>
	</xsl:template>';
						if (isset($subValue['values'])) {
							$xsl .= "\n\t" . '<xsl:template match="xs:element[@name=\'' . $element . '\']//xs:attribute[@name=\'' . $tname . '\']//xs:enumeration">' . "\n";
						}
					}

					if (isset($subValue['values'])) {
						$xsl .= "\t\t" . '<xsl:element name="xs:enumeration">' . "\n\t\t\t" . '<xsl:choose>' . "\n";

						foreach ($subValue['values'] as $from => $to) {
							if ($to !== true) {
								$xsl .= "\t\t\t\t" . '<xsl:when test="@value=\'' . $from . '\'">
					<xsl:attribute name="value">
						<xsl:text>' . $to . '</xsl:text>
					</xsl:attribute>
				</xsl:when>' . "\n";
							}
						}
						$xsl .= "\t\t\t\t" . '<xsl:otherwise>
					<xsl:attribute name="value">
						<xsl:value-of select="./@value" />
					</xsl:attribute>
				</xsl:otherwise>
			</xsl:choose>' . "\n\t\t\t" . '<xsl:apply-templates />' . "\n\t\t" . '</xsl:element>';
						$xsl .= "\n\t" . '</xsl:template>';
					}
				}
			}
		} elseif ($type === 'value') {
			$xsl = '';
			foreach ($translations['found'] as $element => $value) {
				foreach ($value as $tname => $subValue) {
					if (isset($subValue['values'])) {
						if ($element === '*') {
							foreach ($subValue['values'] as $from => $to) {
								$xsl .= '	<xsl:template match="xs:element//xs:attribute[@name=\'' . $subValue['name'] . '\' and @default=\'' . $from . '\']">
		<xsl:element name="xs:attribute">
			<xsl:copy-of select="./@*"/>
			<xsl:attribute name="default">
				<xsl:text>' . $to . '</xsl:text>
			</xsl:attribute>
			<xsl:apply-templates />
		</xsl:element>
	</xsl:template>
';
							}
						} else {
							foreach ($subValue['values'] as $from => $to) {
								$xsl .= '	<xsl:template match="xs:element[@name=\'' . $element . '\']//xs:attribute[@name=\'' . $subValue['name'] . '\' and @default=\'' . $from . '\']">
		<xsl:element name="xs:attribute">
			<xsl:copy-of select="./@*"/>
			<xsl:attribute name="default">
				<xsl:text>' . $to . '</xsl:text>
			</xsl:attribute>
			<xsl:apply-templates />
		</xsl:element>
	</xsl:template>
';
							}
						}
					}
				}
			}
		}
		return sprintf($template, trim($xsl));
	}

	/**
	 * Merge an XML string with the currently loaded stylesheet.
	 *
	 * @param string $xsl The XSL string.
	 * @param string $xml The XML string.
	 * @param string $method The xslt transformation method to use, can be standard or saxon.
	 * @return string A merged string.
	 */
	private function mergeXsl ($xslcontent, $xml = null, $method = 'standard') {
		if ($xml === null) {
			$xml = $this->schema;
		}
		$xsl = new XslUtils($xslcontent);
		return $xsl->mergeXml($xml->xmlGet(Xml::STRING), $method);
	}
}
