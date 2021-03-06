<?php
namespace gimle\spx;

class XmlTranslator
{
	private $xml = false;
	private $translation = false;

	public function __construct ($xml, $translation)
	{
		$this->xml = Xml::load($xml);
		$this->translation = Xml::load($translation);
	}

	/**
	 * Translate the loaded xml elements and attributes to to system english.
	 */
	public function translate ($language, $reverse = false)
	{
		$translations = $this->getElementTranslations($language, $reverse);
		if (empty($translations['found'])) {
			return false;
		}

		$template = $this->genTemplate('elements', $translations);
		$xml = $this->mergeXsl($template, $this->xml->xmlGet(Xml::STRING));

		$translations = $this->getAttributeTranslations($language, true);

		$template = $this->genTemplate('attributes', $translations);
		$xml = $this->mergeXsl($template, $xml);

		$template = $this->genTemplate('value', $translations);
		$xml = $this->mergeXsl($template, $xml);

		return $xml;
	}

	public function getElementNames ()
	{
		$return = array();

		$result = $this->xml->xmlGet(Xml::SIMPLE)->xpath('//*');

		foreach ($result as $value) {
			$return[$value->getName()] = $value->getName();
		}
		return $return;
	}

	public function getAttributeTranslations ($language, $reverse = false)
	{
		$return = array();
		$return['*'] = array();

		$result = $this->translation->xmlGet(Xml::SIMPLE)->xpath('//attribute/' . $language);

		foreach ($result as $value) {
			$tname = (string)$value->attributes()['name'];
			$name = (string)$value->xpath('parent::*')[0]->attributes()['name'];
			$ton = (string)$value->xpath('parent::*')[0]->attributes()['element'];
			if ($tname === '') {
				$tname = $name;
			}
			$return[$ton][$tname]['name'] = $name;
			$children = $value->xpath('parent::*')[0]->values;
			if (isset($children[0])) {
				foreach ($children[0] as $child) {
					$lang = $child->$language->attributes()['name'];
					if (!is_null($lang)) {
						$return[$ton][$tname]['values'][(string)$lang] = (string)$lang->xpath('parent::*')[0]->xpath('parent::*')[0]->attributes()['name'];
					}
				}
			}
		}

		if ($reverse === true) {
			$translations = $this->getElementTranslations($language, true);
			$tmp = array();
			foreach ($return as $key => $value) {
				$subTemp = array();
				foreach ($value as $attr => $attrData) {
					if (isset($attrData['name'])) {
						$subTemp[$attrData['name']]['name'] = $attr;
					}
					if (isset($attrData['values'])) {
						$subSub = array();
						foreach ($attrData['values'] as $keyValue => $valueValue) {
							$subSub[$valueValue] = $keyValue;
						}
						$subTemp[$attrData['name']]['values'] = $subSub;
					}
				}

				if (isset($translations['found'][$key])) {
					$tmp[$translations['found'][$key]] = $subTemp;
				} else {
					$tmp[$key] = $subTemp;
				}
			}
			$return = $tmp;
			unset($tmp);
		}

		return $return;
	}

	public function getElementTranslations ($language, $reverse = false)
	{
		if ($this->xml === false) {
			return false;
		}

		$return = array();

		$result = $this->translation->xmlGet(Xml::SIMPLE)->xpath('//element/' . $language . '/@name');

		$translations = array();
		foreach ($result as $value) {
			$translations[(string)$value['name']] = (string)$value[0]->xpath('parent::*')[0]->xpath('parent::*')[0]->attributes()['name'];
		}
		$return['missing'] = array();
		$return['found'] = array();

		foreach ($this->getElementNames() as $key => $elem) {
			if (!isset($translations[$elem])) {
				$test = (array)$this->translation->xmlGet(Xml::SIMPLE)->xpath('//element[@name=\'' . $elem . '\']');
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
		if ($reverse === true) {
			$tmp = array();
			foreach ($return['found'] as $key => $value) {
				$tmp[$value] = $key;
			}
			$return['found'] = $tmp;
			unset($tmp);
		}

		return $return;
	}

	private function genTemplate ($type, $translations = array())
	{
		$template = '<?xml version="1.0"?>
<xsl:stylesheet version="1.0"
				xmlns:xsl="http://www.w3.org/1999/XSL/Transform"
>
	<!-- xsl:strip-space elements="*" / -->
	<xsl:output method="xml" indent="no" />

	<!-- This template copies everything that doesn\'t have a more specific rule -->
	<xsl:template match="node()|@*">
		<xsl:copy>
			<xsl:apply-templates select="node()|@*" />
		</xsl:copy>
	</xsl:template>

	%s

</xsl:stylesheet>';

		$xsl = '';

		if ($type === 'elements') {
			foreach ($translations['found'] as $from => $to) {
				if ($to === '') {
					continue;
				}
				$xsl .= '
	<xsl:template match="' . $from . '">
		<xsl:element name="' . $to . '">
			<xsl:copy-of select="./@*"/>
			<xsl:apply-templates />
		</xsl:element>
	</xsl:template>';
			}
		} elseif ($type === 'attributes') {
			foreach ($translations as $element => $value) {
				foreach ($value as $tname => $subValue) {
					if ($element === '*') {
						$xsl .= '

<xsl:template match="@' . $tname . '">
	<xsl:attribute name="' . $subValue['name'] . '">
		<xsl:value-of select="."/>
	</xsl:attribute>
</xsl:template>';
					} else {
						$xsl .= '

<xsl:template match="@' . $tname . '[parent::' . $element . ']">
	<xsl:attribute name="' . $subValue['name'] . '">
		<xsl:value-of select="."/>
	</xsl:attribute>
</xsl:template>';
					}
				}
			}
		} elseif ($type === 'value') {
			foreach ($translations as $element => $value) {
				foreach ($value as $tname => $subValue) {
					if (!empty($subValue['values'])) {
						if ($element === '*') {
							$xsl .= '

<xsl:template match="@' . $subValue['name'] . '">
	<xsl:attribute name="' . $subValue['name'] . '">
		<xsl:choose>';
							foreach ($subValue['values'] as $from => $to) {
								$xsl .= '
			<xsl:when test=".=\'' . $from . '\'">
				<xsl:text>' . $to . '</xsl:text>
			</xsl:when>';
							}
							$xsl .= '
			<xsl:otherwise>
				<xsl:value-of select="."/>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:attribute>
</xsl:template>';
						} else {
							$xsl .= '

<xsl:template match="@' . $subValue['name'] . '[parent::' . $element . ']">
	<xsl:attribute name="' . $subValue['name'] . '">
		<xsl:choose>';
							foreach ($subValue['values'] as $from => $to) {
								$xsl .= '
			<xsl:when test=".=\'' . $from . '\'">
				<xsl:text>' . $to . '</xsl:text>
			</xsl:when>';
							}
							if (isset($translations['*'][$tname])) {
								foreach ($translations['*'][$tname]['values'] as $from => $to) {
									if (!isset($subValue['values'][$from])) {
										$xsl .= '
			<xsl:when test=".=\'' . $from . '\'">
				<xsl:text>' . $to . '</xsl:text>
			</xsl:when>';
									}
								}
							}
							$xsl .= '
			<xsl:otherwise>
				<xsl:value-of select="."/>
			</xsl:otherwise>
		</xsl:choose>
	</xsl:attribute>
</xsl:template>';
						}
					}
				}
			}
		}
		return sprintf($template, trim($xsl));
	}

	private function mergeXsl ($xslcontent, $xml = null, $method = 'standard') {
		if ($xml === null) {
			$xml = $this->schema;
		}
		$xsl = new XslUtils($xslcontent);
		return $xsl->mergeXml($xml, $method);
	}
}
