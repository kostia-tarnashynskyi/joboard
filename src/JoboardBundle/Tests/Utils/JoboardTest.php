<?php

namespace JoboardBundle\Tests\Utils;

use JoboardBundle\Utils\Joboard;

class JoboardTest extends \PHPUnit_Framework_TestCase
{
    public function testSlugify()
    {
        $this->assertEquals('company', Joboard::slugify('Company'));
        $this->assertEquals('ooo-company', Joboard::slugify('ooo company'));
        $this->assertEquals('company', Joboard::slugify(' company'));
        $this->assertEquals('company', Joboard::slugify('company '));
        $this->assertEquals('n-a', Joboard::slugify(''));
    }
}