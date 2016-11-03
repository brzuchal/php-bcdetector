<?php
/**
 * Created by PhpStorm.
 * User: mbrzuchalski
 * Date: 03.11.16
 * Time: 13:30
 */
namespace Brzuchal\PHPBC\Detector;

/**
 * Interface Matcher
 * @package Brzuchal\PHPBC\Detector
 * @author Michał Brzuchalski <m.brzuchalski@madkom.pl>
 */
interface Matcher
{
    /**
     * @param string $file
     * @return null|MatcherError
     */
    public function check($file);
}
