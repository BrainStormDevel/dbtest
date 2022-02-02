<?php

class Math
{
	public function addition(float $a, float $b)
	{
		if (($a < 0) || ($b < 0)) {
			return NULL;
		}
		else {
			return $a + $b;
		}
	}
	public function division(float $a, float $b)
	{
		if (($a <= 0) || ($b <= 0)) {
			return NULL;
		}
		else {
			return $a / $b;
		}
	}
	public function compare(float $a, float $b, int $round = 2) : bool
	{
		if (($a < 0) || ($b < 0)) return NULL;
		return ((round($a, $round) === round($b, $round)) ? true : false);
	}
}

$calc = new Math();
$a = 1.1;
$b = 4.7;
$c = $b - $a;
$d = 3.6;
$add = $calc->addition($a, $b);
$addtest = 5.8;
$div = $calc->division($b, $a);
$divzero = $calc->division($b, 0);
$divtest = 4.27;

var_dump($add);
var_dump($calc->compare($add, $addtest, 5));
var_dump($calc->compare($c, $d, 5));
var_dump($div);
var_dump($calc->compare($div, $divtest, 2));
var_dump($divzero);

