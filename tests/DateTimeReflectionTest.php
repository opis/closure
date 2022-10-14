<?php
/* ===========================================================================
 * Copyright (c) 2018-2021 Zindex Software
 *
 * Licensed under the MIT License
 * =========================================================================== */

namespace Opis\Closure\Test;

class DateTimeReflectionTest extends \PHPUnit\Framework\TestCase
{
	public function testDateTime()
	{

		$a = new \DateTime('NOW');
		$b = \Opis\Closure\unserialize(\Opis\Closure\serialize($a));
		$this->assertEquals($a, $b);
	}

	public function testDateTime2()
	{
		$a = [
			'foo' => new \DateTime('NOW'),
			'bar' => function () {
				return 'bar';
			},
			'baz' => 'baz'
		];
		$b = \Opis\Closure\unserialize(\Opis\Closure\serialize($a));
		$this->assertEquals($a, $b);
	}

	public function testDateTime3()
	{
		$a =  function () {
			return new \DateTime('NOW');
		};
		$b = \Opis\Closure\unserialize(\Opis\Closure\serialize($a));
		$this->assertEquals($a, $b);
	}

	public function testCarbon()
	{
		$a = \Carbon\Carbon::now();
		$b = \Opis\Closure\unserialize(\Opis\Closure\serialize($a));
		$this->assertEquals($a, $b);
	}

	public function testCarbon2()
	{
		$a = [
			'foo' => \Carbon\Carbon::now(),
			'bar' => function () {
				return 'bar';
			},
			'baz' => 'baz'
		];
		$b = \Opis\Closure\unserialize(\Opis\Closure\serialize($a));
		$this->assertEquals($a, $b);
	}

	public function testCarbon3()
	{
		$a =  function () {
			return \Carbon\Carbon::now();
		};
		$b = \Opis\Closure\unserialize(\Opis\Closure\serialize($a));
		$this->assertEquals($a, $b);
	}
}
