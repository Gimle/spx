<?php
namespace gimle\spx;

interface XmlInterface
{
	const FILE = 1;
	const STRING = 2;
	const SIMPLE = 3;
	const WRITER = 4;
	const DOM = 5;

	const E_UNKNOWN = 1;
	const E_INPUT = 2;
	const E_NOTFOUND = 3;
	const E_READ = 4;
	const E_WRITE = 5;
	const E_WELLFORMED = 6;
	const E_VALID = 7;
	const E_TIMEOUT = 8;
	const E_XPATH = 9;
	const E_METHODNOTFOUND = 10;
}
