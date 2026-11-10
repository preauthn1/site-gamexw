<?php


require_once(__DIR__."/utils/integer.php");
class Point
{
    public $x, $y, $z;

    function __construct($x=0, $y=0, $z=0)
    {
        $model = new Integer();
        $this->x = $model::toBigInt($x);
        $this->y = $model::toBigInt($y);
        $this->z = $model::toBigInt($z);
    }

    function __toString()
    {
        return "({$this->x}, {$this->y}, {$this->z})";
    }

    function isAtInfinity()
    {
        return $this->y == 0;
    }
}
