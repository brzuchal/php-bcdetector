<?php
/**
 * Created by PhpStorm.
 * User: mbrzuchalski
 * Date: 31.10.16
 * Time: 12:11
 */
namespace Brzuchal\PHPBC\Command;

use Brzuchal\PHPBC\Detector;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Class DetectBC
 * @package Brzuchal\PHPBC\Command
 * @author Michał Brzuchalski <m.brzuchalski@madkom.pl>
 */
class DetectBreakCompatibilityCommand extends Command
{
    /** @var Filesystem */
    private $filesystem;
    /** @var array */
    private $tested = [];

    public function configure()
    {
        $this->filesystem = new Filesystem();
        $this->setName('detect');
        $this->addArgument('repositories', InputArgument::IS_ARRAY ^ InputArgument::OPTIONAL, 'Repositories url to check');
        $this->addOption('dir', 'd', InputOption::VALUE_OPTIONAL, 'Working directory for repoo clones', $this->filesystem->makePathRelative(__DIR__, realpath(__DIR__ . "/../../repos")));
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $repositories = $input->getArgument('repositories');

        $dir = sizeof($repositories) ? $input->getOption('dir') : null;

        if (sizeof($repositories) && !empty($dir)) {
            if (!$this->filesystem->exists($dir)) {
                $this->filesystem->mkdir($dir);
            }
            $output->writeln(sprintf(
                '<info>Going to clone </info><comment>%d</comment> <info>repository(ies) into </info><comment>%s</comment>',
                sizeof($repositories),
                $dir
            ));
            foreach ($repositories as $repository) {
                $path = str_replace(['.git', 'git@github.com:'], ['', ''], parse_url($repository, PHP_URL_PATH));
                $this->filesystem->mkdir($dir . DIRECTORY_SEPARATOR . $path);
                $affected = $this->processDetection($output, $repository, $dir . DIRECTORY_SEPARATOR . $path);
                $this->tested[$path] = ['project' => $path, 'repository' => $repository, 'errors' => sizeof($affected)];
            }
            $count = sizeof($this->tested);
            $output->writeln("<info>Examined {$count} projects</info>");
            $this->printSummary($output);
        }
    }

    /**
     * @param OutputInterface $output
     * @param $repository
     * @param $dir
     * @return array|null
     */
    private function processDetection(OutputInterface $output, $repository, $dir)
    {
        $detector = new Detector($repository, $dir);
        $output->writeln("<info>Cloninig</info> <comment>{$repository}</comment>...");
        if ($detector->fetch()) {
            $output->writeln("<info>Detecting break compatibilities</info> at <comment>{$dir}</comment>...");
            $detector->run();
        } else {
            $output->writeln('<error>Error: Unable to clone</error>');
        }
        if ($detector->hasErrors()) {
            $output->writeln("<error>Error: There are some potential compatibility breaks on version: {$detector->getCurrentVersion()}</error>");
            $this->printErrorsTable($output, $detector->getErrors());
            return $detector->getErrors();
        }
        return null;
    }

    /**
     * @param OutputInterface $output
     * @param array $errors
     */
    private function printErrorsTable(OutputInterface $output, array $errors)
    {
        $headers = array('Error', 'File', 'Line', 'Affected Version');
        $rows = array_map(function ($error) {
            return [$error['msg'], $error['file'], $error['line'], (string)$error['version']];
        }, $errors);

        if (class_exists('\\Symfony\\Component\\Console\\Helper\\Table')) {
            $table = new Table($output);
            $table->setHeaders($headers);
            $table->setRows($rows);
            $table->render();
        } else {
            $table = $this->getHelper('table');
            $table->setHeaders($headers);
            $table->setRows($rows);
            $table->render($output);
        }
    }

    private function printSummary(OutputInterface $output)
    {
        $headers = array('Project', 'GitHub', 'Errors');
        $rows = array_map(function ($error) {
            return [$error['project'], $error['repository'], $error['errors']];
        }, $this->tested);

        if (class_exists('\\Symfony\\Component\\Console\\Helper\\Table')) {
            $table = new Table($output);
            $table->setHeaders($headers);
            $table->setRows($rows);
            $table->render();
        } else {
            $table = $this->getHelper('table');
            $table->setHeaders($headers);
            $table->setRows($rows);
            $table->render($output);
        }
    }
}
