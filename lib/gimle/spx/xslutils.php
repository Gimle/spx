<?php
namespace gimle\spx;

class XslUtils
{
	private $stylesheet = false;

	public function __construct ($stylesheet)
	{
		$this->stylesheet = $stylesheet;
	}

	/**
	 * Merge an XML string with the currently loaded stylesheet.
	 *
	 * @param string $xml The XML string.
	 * @param string $method The xslt transformation method to use, can be standard or saxon.
	 * @return string A merged xml.
	 */
	public function mergeXml ($xml, $method = 'standard')
	{
		if ($method === 'standard') {
			$xsl = new \XSLTProcessor();
			if (ENV_LEVEL & ENV_DEV) {
				$olderrorhandler = libxml_use_internal_errors(true);
			}
			$xsldoc = new \DOMDocument();
			$xsldoc->loadXML($this->stylesheet);
			$xsl->importStyleSheet($xsldoc);

			$xmldoc = new \DOMDocument();
			$xmldoc->loadXML($xml);
			if (ENV_LEVEL & ENV_DEV) {
				$errors = libxml_get_errors();
				if (!empty($errors)) {
					foreach ($errors as $error) {
						$lines = explode("\n", $xml);
						echo '<div style="margin: 10px;">';
						echo '<b>' . $error->message . '</b><br>';
						if ($error->line > 1) {
							echo '<div style="color: #666;">' . htmlspecialchars($lines[$error->line - 2]) . '</div>';
						}
						echo '<div style="color: #c00;">' . htmlspecialchars($lines[$error->line - 1]) . '</div>';
						if (isset($lines[$error->line])) {
							echo '<div style="color: #666;">' . htmlspecialchars($lines[$error->line]) . '</div>';
						}
						echo '</div>';
					}
					libxml_clear_errors();
				}
				libxml_use_internal_errors($olderrorhandler);
			}
			return $xsl->transformToXML($xmldoc);
		} elseif ($method === 'saxon') {
			$xslfile = \gimle\common\make_temp_file();
			$xmlfile = \gimle\common\make_temp_file();
			$resultfile = \gimle\common\make_temp_file();
			file_put_contents($xslfile, $this->stylesheet);
			file_put_contents($xmlfile, $xml);

			$result = \gimle\common\run('saxon-xslt -o ' . $resultfile . ' ' . $xmlfile . ' ' . $xslfile);
			$return = file_get_contents($resultfile);

			if ($result['sterr'][0] !== '') {
				foreach ($result['sterr'] as $error) {
					trigger_error($error, E_USER_WARNING);
				}
			}

			unlink($xslfile);
			unlink($xmlfile);
			unlink($resultfile);
			return $return;
		}
	}
}
