<?php

namespace PSR2R\Tests\Classes;

use PSR2R\Base\AbstractBase;

class SelfAccessorUnitTestInc {
	private static $one;
	private $two;
	public function method() {
		$a = self::$one;
		$b = static::$one;
		$c = static::$two;
		$d = $this->two;
		SelfAccessorUnitTestInc::smethod();
		static::smethod();
		self::smethod();
	}

	public static function smethod() {

	}
}
trait SelfTrait {
	public function mTrait() {
		SelfTrait::sTrait();
		SelfInterface::sInt();
	}
	public static function sTrait() {

	}
}
interface SelfInterface {
	public static function sInt();
}
