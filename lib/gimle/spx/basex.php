<?php
namespace gimle\spx;

use \gimle\common\System;

class BaseX {
	private static $basexconnections = array();

	public static function get ($key) {
		if ((!array_key_exists($key, self::$basexconnections)) || (!self::$basexconnections[$key] instanceof BaseXLib)) {
			self::$basexconnections[$key] = new BaseXLib(System::$config['basex'][$key]);
		}
		return self::$basexconnections[$key];
	}
}
