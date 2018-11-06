<?php
// +----------------------------------------------------------------------
// | TopThink [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2016 http://www.topthink.com All rights reserved.
// +----------------------------------------------------------------------
// | Author: zhangyajun <448901948@qq.com>
// +----------------------------------------------------------------------

namespace think\migration\command\migrate;

use Phinx\Migration\MigrationInterface;
use think\console\Input;
use think\console\input\Definition;
use think\console\input\Option as InputOption;
use think\console\Output;
use think\migration\command\Migrate;

class Run extends Migrate
{
    protected $format = 'database';

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('migrate:run')
            ->setDescription('Migrate the database')
            ->addOption('--target', '-t', InputOption::VALUE_REQUIRED, 'The version number to migrate to')
            ->addOption('--date', '-d', InputOption::VALUE_REQUIRED, 'The date to migrate to')
            ->addOption('--connection', '-c', InputOption::VALUE_REQUIRED, 'The database connection to migrate to')
            ->addOption('--dry-run', '', InputOption::VALUE_NONE, 'Print the queries to standard output without executing them')
            ->addOption('--sql', '-s', InputOption::VALUE_NONE, 'Log the queries to sql file without executing them')
            ->setHelp(<<<EOT
The <info>migrate:run</info> command runs all available migrations, optionally up to a specific version

<info>php console migrate:run</info>
<info>php console migrate:run -t 20110103081132</info>
<info>php console migrate:run -d 20110103</info>
<info>php console migrate:run -v</info>

EOT
            );
    }

    /**
     * Migrate the database.
     *
     * @param Input  $input
     * @param Output $output
     * @return integer integer 0 on success, or an error code.
     */
    protected function execute(Input $input, Output $output)
    {
        $version    = $input->getOption('target');
        $date       = $input->getOption('date');
        $connection = $input->getOption('connection');
        $isDryRun   = $input->getOption('dry-run');
        $sql        = $input->getOption('sql');

        if ($isDryRun or $sql) {

            if ($isDryRun) {
                $this->format = 'console';
            }
            if ($sql) {
                $this->format = 'file';
            }

            $this->setDryRunInput()->setFileOutput();
        }

        $this->setConnection($connection);

        // run the migrations
        $start = microtime(true);
        if (null !== $date) {
            $this->migrateToDateTime(new \DateTime($date));
        } else {
            $this->migrate($version);
        }
        $end = microtime(true);

        $output->writeln('');
        $output->writeln('<comment>All Done. Took ' . sprintf('%.4fs', $end - $start) . '</comment>');
    }

    public function migrateToDateTime(\DateTime $dateTime)
    {
        $versions   = array_keys($this->getMigrations());
        $dateString = $dateTime->format('YmdHis');

        $outstandingMigrations = array_filter($versions, function ($version) use ($dateString) {
            return $version <= $dateString;
        });

        if (count($outstandingMigrations) > 0) {
            $migration = max($outstandingMigrations);
            $this->output->writeln('Migrating to version ' . $migration);
            $this->migrate($migration);
        }
    }

    protected function migrate($version = null)
    {
        $migrations     = $this->getMigrations();
        $hasSchemaTable = $this->hasSchemaTableWithoutCreating();
        $versions       = $this->format !== 'database' && !$hasSchemaTable ? [] : $this->getVersions();
        $current        = $this->format !== 'database' && !$hasSchemaTable ? 0 : $this->getCurrentVersion();

        if (empty($versions) && empty($migrations)) {
            return;
        }

        if (null === $version) {
            $version = max(array_merge($versions, array_keys($migrations)));
        } else {
            if (0 != $version && !isset($migrations[$version])) {
                $this->output->writeln(sprintf('<comment>warning</comment> %s is not a valid version', $version));
                return;
            }
        }

        // are we migrating up or down?
        $direction = $version > $current ? MigrationInterface::UP : MigrationInterface::DOWN;

        if ($direction === MigrationInterface::DOWN) {
            // run downs first
            krsort($migrations);
            foreach ($migrations as $migration) {
                if ($migration->getVersion() <= $version) {
                    break;
                }

                if (in_array($migration->getVersion(), $versions)) {
                    if ($this->format === 'file') {
                        $this->getAdapter()->getOutput()->setName($migration->getName())->newFile();
                    }
                    $this->executeMigration($migration, MigrationInterface::DOWN);
                }
            }
        }

        ksort($migrations);
        foreach ($migrations as $migration) {
            if ($migration->getVersion() > $version) {
                break;
            }

            if (!in_array($migration->getVersion(), $versions)) {
                if ($this->format === 'file') {
                    $this->getAdapter()->getOutput()->setName($migration->getName())->newFile();
                }
                $this->executeMigration($migration, MigrationInterface::UP);
            }
        }
    }

    protected function getCurrentVersion()
    {
        $versions = $this->getVersions();
        $version  = 0;

        if (!empty($versions)) {
            $version = end($versions);
        }

        return $version;
    }

    protected function hasSchemaTableWithoutCreating()
    {
        if ($this->format !== 'database') {
            $output         = $this->getAdapter()->getOutput();
            $hasSchemaTable = $this->getAdapter()
                ->setOutput(new Output('nothing'))
                ->hasSchemaTable();
            $this->getAdapter()->setOutput($output);
            return $hasSchemaTable;
        }
        return $this->getAdapter()->hasSchemaTable();
    }

    protected function setFileOutput()
    {
        $this->getAdapter()->setOutput(new Output('file'));
        return $this;
    }

    protected function setDryRunInput()
    {
        $defdefinition = new Definition();
        $defdefinition->addOption(new InputOption('--dry-run', '', InputOption::VALUE_NONE, '', null));

        $input = new Input([]);
        $input->bind($defdefinition);
        $input->setOption('dry-run', true);

        $this->getAdapter()->setInput($input);
        return $this;
    }
}
