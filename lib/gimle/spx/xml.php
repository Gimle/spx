<?php
namespace gimle\spx;

use gimle\common\System;

/**
 * How to connect:
 * Xml::get(); // Checks up site name, and loads the file corresponding to that.
 * Xml::get('config.file.lookup'); // Run this thru the connector wrapper.
 * Xml::get('string, object or filename.', 'key');
 * Xml::get(array()); // Extended information about what to load.
 *
 * new Xml('string, array(), object or filename.');
*/
class Xml implements XmlInterface
{
	private static $connections = array();

	public static function load ($input)
	{
		if (!is_array($input)) {
			return new XmlFile($input);
		}
		if (!isset($input['class'])) {
			$input['class'] = '\gimle\spx\XmlFile';
		}
		return new $input['class']($input['xml']);
	}

	public static function get ($value = false, $key = false)
	{
		if ($value === false) {
			// Checks up site name, and loads the file corresponding to that.
			return;
		}

		$type = 'key';
		if ($key === false) {
			if (is_string($value)) {
				$type = 'config';
				$key = $value;
				$value = \gimle\common\locate_in_array_by_string(System::$config, $value);
			} else {
				$type = 'generated';
				$key = json_encode($value);
			}
		}

		if ((!isset(self::$connections[$type])) || (!array_key_exists($key, self::$connections[$type]))) {
			if (!is_array($value)) {
				$value['xml'] = $value;
			}
			if (!isset($value['class'])) {
				$value['class'] = '\gimle\spx\XmlFile';
			}

			$result = new $value['class']($value['xml']);
			self::$connections[$type][$key] = $result;
		}

		return self::$connections[$type][$key];
	}
}
