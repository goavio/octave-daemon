<?php

require_once "common_phpunit_test.php";

class adhocTest extends commonTests
{
	static $octave=NULL;

	function __construct()
	{
		if (!$this->init())
			return;

		self::$octave=new Octave(false);
	}
}