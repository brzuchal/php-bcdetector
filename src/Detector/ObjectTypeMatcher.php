<?php
/**
 * Created by PhpStorm.
 * User: mbrzuchalski
 * Date: 03.11.16
 * Time: 13:31
 */
namespace Brzuchal\PHPBC\Detector;

/**
 * Class ObjectTypeMatcher
 * @package Brzuchal\PHPBC\Detector
 * @author Michał Brzuchalski <m.brzuchalski@madkom.pl>
 */
class ObjectTypeMatcher implements Matcher
{
    const RESERVED_NAME = 'Object';
    /**
     * @param string $file
     * @return null|MatcherError
     */
    public function check($file)
    {
        if ($content = file_get_contents($file)) {
            $content = preg_replace('/((\s+\*\s+)[^\n]+\n)/siU', "\\2\n", $content);
            if (preg_match('/.*(class|interface|trait)\s+(' . self::RESERVED_NAME . ')[\s\{]/siU', $content, $match, PREG_OFFSET_CAPTURE)) {
                $line = substr_count($content, "\n", 0, $match[2][1]);
                return new MatcherError("Reserved '" . self::RESERVED_NAME . "' as {$match[1][0]} name", $file, $line);
            }
        }
        return null;
    }
}
