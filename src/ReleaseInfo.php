<?php
namespace TheRat\SymDep;

use TheRat\SymDep\ReleaseInfo\Issue;
use TheRat\SymDep\ReleaseInfo\Jira;
use TheRat\SymDep\ReleaseInfo\LogParser;

/**
 * Class ReleaseInfo
 *
 * @package TheRat\SymDep
 */
class ReleaseInfo
{
    const PARAMETER_JIRA_ISSUES = 'jira-issues';
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
     * @var Jira
     */
    protected $jira;

    /**
     * ReleaseInfo constructor.
     */
    public function __construct()
    {
        $jiraUrl = null;
        $jiraCredentials = null;
        if (has('jira-url') && has('jira-credentials')) {
            $this->jira = new Jira(get('jira-url'), get('jira-credentials'));
        } else {
            writeln(
                '<error>Jira plugin does not work,'
                .' because "jira-url" and "jira-credentials" options are not provided</error>'
            );
        }
        $this->setCurrentLink(env()->parse('{{deploy_path}}/current'));
        $this->localDeployPath = dirname(dirname(dirname(dirname(__DIR__))));
        $this->logParser = new LogParser();
    }

    /**
     * @return string
     */
    public function getLocalDeployPath()
    {
        return $this->localDeployPath;
    }

    /**
     * @return Jira
     */
    public function getJira()
    {
        return $this->jira;
    }

    /**
     * @return LogParser
     */
    public function getLogParser()
    {
        return $this->logParser;
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
     *
     */
    public function run()
    {
        $this->checkCurrentDeployDir();
        $log = $this->getDiffLog();
        $countLog = count($log);

        if ($countLog) {
            $taskNameList = $this->getLogParser()->execute($log);
            $countTask = count($taskNameList);

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

            if ($countTask && $this->getJira()) {
                $issues = $this->getJira()->generateIssues($taskNameList);
                if ($issues) {
                    set(self::PARAMETER_JIRA_ISSUES, $issues);
                    $this->showIssues();
                }
            }

            writeln('');
            if (askConfirmation('Would you like to continue deploy on prod')) {

            } else {
                throw new \RuntimeException('Deploy canceled');
            }
        } else {
            $message = 'There are no changes between current directory and remote';
            throw new \RuntimeException($message);
        }
    }

    /**
     *
     */
    public function showIssues()
    {
        if (has(self::PARAMETER_JIRA_ISSUES)) {
            $issues = get(self::PARAMETER_JIRA_ISSUES);
            writeln('Jira:');
            foreach ($issues as $issue) {
                /**
                 * @var Issue $issue
                 */
                writeln(' * '.$issue->__toString());
            }
        }
    }

    /**
     *
     */
    protected function checkCurrentDeployDir()
    {
        $cmd = sprintf('cd %s && git rev-parse --abbrev-ref HEAD', $this->getLocalDeployPath());
        if ('master' !== trim(runLocally($cmd)->toString())) {
            throw new \RuntimeException('Current deploy path is not master');
        }
    }

    /**
     * @return array
     */
    protected function getDiffLog()
    {
        $cmd = sprintf('cd %s && git rev-parse --verify HEAD', $this->getCurrentLink());
        $remoteRevision = trim(run($cmd)->toString());

        runLocally('git pull origin master');
        $cmd = sprintf('git log %s..HEAD --pretty=format:"[%%h]|(%%cE): %%s"', $remoteRevision);
        $log = runLocally($cmd)->toArray();

        return $log;
    }
}
