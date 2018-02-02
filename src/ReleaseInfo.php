<?php

namespace TheRat\SymDep;

use TheRat\SymDep\ReleaseInfo\LogParser;
use function Deployer\askConfirmation;
use function Deployer\get;
use function Deployer\has;
use function Deployer\parse;
use function Deployer\run;
use function Deployer\runLocally;
use function Deployer\set;
use function Deployer\writeln;

/**
 * Class ReleaseInfo
 *
 * @package TheRat\SymDep
 */
class ReleaseInfo
{
    const PARAMETER_TASK_LIST = 'release_info_task_list';

    /**
     * @var self
     */
    protected static $instance;

    /**
     * @var string
     */
    protected $currentLink;

    /**
     * @var string
     */
    protected $localDeployPath;

    /**
     * @var LogParser
     */
    protected $logParser;

    /**
     * @var bool
     */
    protected $executed = false;

    /**
     * ReleaseInfo constructor.
     */
    protected function __construct()
    {
        $this->setCurrentLink(parse('{{deploy_path}}/current'));
        $this->localDeployPath = dirname(get('deploy_file'));
        $this->logParser = new LogParser();
    }

    /**
     * @return self
     */
    public static function getInstance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     *
     */
    public function run()
    {
        if ($this->executed) {
            return;
        }
        if (!$this->checkCurrentDeployDir()) {
            return;
        }
        $log = $this->getDiffLog();
        $countLog = count($log);

        if ($countLog) {
            $taskNameList = $this->getLogParser()->execute($log);

            $info = sprintf(
                '<info>Found %d revision and %d tasks</info>',
                $countLog,
                count($taskNameList)
            );
            writeln($info);

            writeln('Git log:');
            foreach ($log as $item) {
                writeln(' * '.$item);
            }

            writeln('Task list:');
            foreach ($taskNameList as $item) {
                writeln(' * '.$item);
            }

            set(self::PARAMETER_TASK_LIST, $taskNameList);

        } else {
            $message = 'There are no changes between current directory and remote';
            throw new \RuntimeException($message);
        }
        $this->executed = true;
    }

    /**
     * @return string
     */
    public function getLocalDeployPath()
    {
        return $this->localDeployPath;
    }

    /**
     * @return mixed
     */
    public function getCurrentLink()
    {
        return $this->currentLink;
    }

    /**
     * @param mixed $currentLink
     *
     * @return self
     */
    public function setCurrentLink($currentLink)
    {
        if (!FileHelper::dirExists($currentLink)) {
            throw new \RuntimeException('Current link "'.$currentLink.'" does not exists');
        }
        $this->currentLink = $currentLink;

        return $this;
    }

    /**
     * @return LogParser
     */
    public function getLogParser()
    {
        return $this->logParser;
    }

    /**
     *
     */
    public function showIssues()
    {
        if ($this->executed) {
            return;
        }

        if (has(self::PARAMETER_TASK_LIST) && get(self::PARAMETER_TASK_LIST)) {
            writeln('Deployed:');
            foreach (get(self::PARAMETER_TASK_LIST) as $task) {
                writeln(' * '.$task);
            }
        }
    }

    /**
     *
     */
    protected function checkCurrentDeployDir()
    {
        $cmd = sprintf('cd %s && git rev-parse --abbrev-ref HEAD', $this->getLocalDeployPath());
        if ('master' !== trim(runLocally($cmd))) {
            writeln('<error>Current deploy path is not master</error>');

            return false;
        }

        return true;
    }

    /**
     * @return array
     */
    protected function getDiffLog()
    {
        $cmd = sprintf('cd %s && git rev-parse --verify HEAD', $this->getCurrentLink());
        $remoteRevision = trim(run($cmd));

        runLocally('git pull origin master');
        $cmd = sprintf('git log %s..HEAD --pretty=format:"[%%h]|(%%cE): %%s"', $remoteRevision);
        $log = explode("\n", runLocally($cmd));

        return $log;
    }
}
