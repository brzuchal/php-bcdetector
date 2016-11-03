<?php
/**
 * Created by PhpStorm.
 * User: mbrzuchalski
 * Date: 03.11.16
 * Time: 13:35
 */
namespace Brzuchal\PHPBC\Detector;

/**
 * Class Error
 * @package Brzuchal\PHPBC\Detector
 * @author Michał Brzuchalski <m.brzuchalski@madkom.pl>
 */
class MatcherError
{
    private $message;
    private $file;
    private $line;

    public function __construct($message, $file, $line)
    {
        $this->message = $message;
        $this->file = $file;
        $this->line = $line;
    }

    /**
     * @return mixed
     */
    public function getMessage()
    {
        return $this->message;
    }

    /**
     * @return mixed
     */
    public function getFile()
    {
        return $this->file;
    }

    /**
     * @return mixed
     */
    public function getLine()
    {
        return $this->line;
    }
}
