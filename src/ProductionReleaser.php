<?php

namespace TheRat\SymDep;

use function Deployer\isVerbose;
use function Deployer\run;
use function Deployer\runLocally;
use function Deployer\writeln;

/**
 * Class Release
 * @package TheRat\SymDep
 */
class ProductionReleaser
{
    /**
     * @var self
     */
    protected static $instance;
    /**
     * @var
     */
    protected $releaseBranch;
    /**
     * @var array
     */
    protected $deletedBranches;

    /**
     * @var bool
     */
    protected $executed = false;

    /**
     * ProductionReleaser constructor.
     */
    protected function __construct()
    {

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
     * @return mixed
     */
    public function getReleaseBranch()
    {
        return $this->releaseBranch;
    }

    /**
     * @return string
     */
    public function createReleaseBranch()
    {
        if (!$this->releaseBranch) {
            if ('master' !== $this->getLocalBranch()) {
                throw new \RuntimeException('This operation only for master, please switch branch');
            }

            $subject = $this->getBranches();
            $pattern = '/release-(\d+)/i';
            $matches = null;
            preg_match_all($pattern, $subject, $matches);
            $releaseVersions = empty($matches[1]) ? [] : $matches[1];
            natsort($releaseVersions);

            $releaseVersion = 1;
            if (!empty($releaseVersions)) {
                $releaseVersion = (int)array_pop($releaseVersions) + 1;
            }
            $releaseBranchName = 'RELEASE-'.$releaseVersion;

            runLocally(sprintf('{{bin/git}} checkout -b %s', $releaseBranchName));
            runLocally(sprintf('{{bin/git}} push origin %s', $releaseBranchName));
            runLocally('{{bin/git}} checkout master');

            $this->releaseBranch = $releaseBranchName;
        }

        return $this->releaseBranch;
    }

    /**
     * @return string
     */
    public function getLocalBranch()
    {
        return runLocally('{{bin/git}} rev-parse --abbrev-ref HEAD');
    }

    /**
     * @return string
     */
    public function getBranches()
    {
        runLocally('{{bin/git}} fetch && {{bin/git}} fetch -p && {{bin/git}} pull');
        $subject = runLocally('{{bin/git}} ls-remote');

        return $subject;
    }

    /**
     * @param int $keepReleases
     * @return array
     */
    public function deleteReleaseBranches($keepReleases = 5)
    {
        if (!$this->deletedBranches && !$this->executed) {
            $this->executed = true;
            $subject = $this->getBranches();
            $pattern = '/(release-\d+)/i';
            $matches = null;
            preg_match_all($pattern, $subject, $matches);
            $releasesBranches = empty($matches[1]) ? [] : $matches[1];
            natsort($releasesBranches);
            $forDelete = array_slice($releasesBranches, 0, -2 * (int)$keepReleases);
            if ($forDelete) {
                runLocally('{{bin/git}} push origin --delete '.implode(' ', $forDelete));
                try {
                    runLocally('{{bin/git}} branch -D '.implode(' ', $forDelete));
                } catch (\Exception $e) {
                    !isVerbose() ?: writeln($e->getMessage());
                }
            }

            $this->deletedBranches = $forDelete;
        }

        return $this->deletedBranches;
    }

    /**
     * @param string $dir
     * @return array
     */
    public function getReleaseList($dir)
    {
        // find will list only dirs in releases/
        $releaseList = explode("\n", run("find \"$dir\" -maxdepth 1 -mindepth 1 -type d"));
        // filter out anything that does not look like a release
        foreach ($releaseList as $key => $item) {
            $item = basename($item); // strip path returned from find
            // release dir can look like this: 20160216152237 or 20160216152237.1.2.3.4 ...
            $name_match = '[0-9]{14}'; // 20160216152237
            $extension_match = '\.[0-9]+'; // .1 or .15 etc
            if (!preg_match("/^$name_match($extension_match)*$/", $item)) {
                unset($releaseList[$key]); // dir name does not match pattern, throw it out
                continue;
            }
            $releaseList[$key] = $item; // $item was changed
        }
        rsort($releaseList);

        return $releaseList;
    }

    /**
     * @return mixed
     */
    public function getLastReleaseBranch()
    {
        $subject = $this->getBranches();
        $pattern = '/(release-\d+)/i';
        $matches = null;
        preg_match_all($pattern, $subject, $matches);
        $releasesBranches = empty($matches[1]) ? [] : $matches[1];
        natsort($releasesBranches);

        return array_pop($releasesBranches);
    }
}
