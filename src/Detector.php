<?php
/**
 * Created by PhpStorm.
 * User: mbrzuchalski
 * Date: 31.10.16
 * Time: 14:24
 */
namespace Brzuchal\PHPBC;

use Brzuchal\PHPBC\Detector\Matcher;
use Brzuchal\PHPBC\Detector\MatcherError;
use Brzuchal\PHPBC\Detector\ObjectTypeMatcher;
use InvalidArgumentException;
use Naneau\SemVer\Version;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RecursiveRegexIterator;
use RegexIterator;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Naneau\SemVer\Parser;
use Naneau\SemVer\Compare;


/**
 * Class Detector
 * @package Brzuchal\PHPBC
 * @author Michał Brzuchalski <m.brzuchalski@madkom.pl>
 */
class Detector
{
    /** @var string */
    private $repository;
    /** @var string */
    private $dir;
    /** @var array */
    private $errors = [];
    /** @var Filesystem */
    private $filesystem;
    /** @var Matcher[] */
    private $matchers;
    /** @var Version */
    private $currentVersion;

    /**
     * Detector constructor.
     * @param string $repository
     * @param string $dir
     */
    public function __construct($repository, $dir)
    {
        $this->repository = $repository;
        $this->dir = $dir;
        $this->filesystem = new Filesystem();
        $this->addMatcher(new ObjectTypeMatcher());
    }

    public function addMatcher(Matcher $matcher)
    {
        $this->matchers[] = $matcher;
    }

    public function fetch()
    {
        if ($this->filesystem->exists($this->dir . DIRECTORY_SEPARATOR  . '.git')) {
            return true;
        }
        $clone = new Process("git clone {$this->repository} {$this->dir}");
        $clone->run();
        if ($clone->getExitCode()) {
            $this->error("Unable to clone {$this->repository} into {$this->dir}");
        }
        return $clone->getExitCode() == 0;
    }

    /**
     * @return void
     */
    public function run()
    {
        $tags = $this->getTags();
        foreach ($tags as $tag) {
            $this->currentVersion = Parser::parse($tag[0] == 'v' ? substr($tag, 1) : $tag);
            if (!($this->currentVersion->hasPreRelease()) && $this->checkout($tag)) {
                $sourceDirectory = $this->getSourceDirectory();
                $errors = $this->doRun($sourceDirectory);
                if (sizeof($errors) > 0) {
                    /** @var MatcherError $error */
                    foreach ($errors as $error) {
                        $this->error($error->getMessage(), $error->getFile(), $error->getLine());
                    }
//                    dump($sourceDirectory);
//                    dump($errors, $this->errors);
//                    die("fertig");
                }
            }
        }
//        dump($this->e);
    }

    /**
     * @return array
     */
    public function getTags()
    {
        $versions = [];
        $tags = new Process('git tag', $this->dir);
        $tags->run();
        if ($tags->getExitCode() == 0) {
            $versions = explode(PHP_EOL, $tags->getOutput());
            $versions = array_filter($versions, function ($version) {
                try {
                    Parser::parse($version);
                } catch (InvalidArgumentException $exception) {
                    return 0;
                }
                return !(stripos($version, 'beta') !== false || stripos($version, 'dev') !== false) && !empty($version);
            });

            uasort($versions, function ($a, $b) {
                $a = $a[0] == 'v' ? substr($a, 1) : $a;
                $b = $b[0] == 'v' ? substr($b, 1) : $b;
                if (Compare::greaterThan(Parser::parse($a),  Parser::parse($b))) {
                    return -1;
                }
                if (Compare::smallerThan(Parser::parse($a),  Parser::parse($b))) {
                    return 1;
                }
                return 0;
            });
        }

        return $versions;
    }

    /**
     * @return bool
     */
    public function hasErrors()
    {
        return sizeof($this->errors);
    }

    /**
     * @return array
     */
    public function getErrors()
    {
        return $this->errors;
    }

    /**
     * @param string $message
     */
    private function error($message, $file = null, $line = null)
    {
        $version = (string)$this->currentVersion;
        $hash = md5("{$message}#{$file}@{$line}");
        if (array_key_exists($hash, $this->errors)) {
            return;
        }
        $this->errors[$hash] = ['msg' => $message, 'file' => $file, 'line' => $line, 'version' => $version];
    }

    /**
     * @param string $tag
     * @return bool
     */
    private function checkout($tag)
    {
        $checkout = new Process("git checkout {$tag}", $this->dir);
        $checkout->run();
        if ($checkout->getExitCode() > 0) {
            $this->error("Unable to checkout {$tag} tag in {$this->dir}");
            return false;
        }

        return true;
    }

    /**
     * @return null|string
     */
    private function getSourceDirectory()
    {
        $composerFilepath = $this->dir . DIRECTORY_SEPARATOR . 'composer.json';
        if ($this->filesystem->exists($composerFilepath)) {
            $composer = json_decode(file_get_contents($composerFilepath));
            if (property_exists($composer, 'autoload')) {
                if (property_exists($composer->autoload, 'psr-4')) {
                    foreach ($composer->autoload->{'psr-4'} as $ns => $dir) {
                        return $this->dir . DIRECTORY_SEPARATOR . $dir;
                    }
                }
            }
        }

        return null;
    }

    /**
     * @param $sourceDirectory
     * @return array
     */
    private function doRun($sourceDirectory)
    {
        if (empty($sourceDirectory)) {
            return [];
        }
        $directory = new RecursiveDirectoryIterator($sourceDirectory);
        $iterator = new RecursiveIteratorIterator($directory);
        $regex = new RegexIterator($iterator, '/^.+\.php$/i', RecursiveRegexIterator::GET_MATCH);
        $errors = [];

        foreach ($regex as $files) {
            foreach ($files as $file) {
                /** @var Matcher $matcher */
                foreach ($this->matchers as $matcher) {
                    if ($error = $matcher->check($file)) {
                        $errors[] = $error;
                    }
                }
            }
        }

        return $errors;
    }

    /**
     * @return Version
     */
    public function getCurrentVersion()
    {
        return $this->currentVersion;
    }
}
