<?php
namespace TheRat\SymDep;

use Deployer\Deployer;
use Symfony\Component\Console\Input\InputInterface;
use TheRat\SymDep\Helper\Crontab;
use TheRat\SymDep\Helper\GenerateFile;
use TheRat\SymDep\Helper\Shell;
use TheRat\SymDep\Helper\ShellExec;

class SymDep
{
    public static function getCurrentBranch(InputInterface $input = null)
    {
        $branch = get('branch', false);
        if (!$branch) {
            $branch = $input->getOption('branch');
            if (!$branch) {
                $branch = trim(ShellExec::runLocally('git rev-parse --abbrev-ref HEAD', false));
            }
            set('branch', $branch);
        }
        return $branch;
    }

    public static function console($command)
    {
        $releasePath = env()->getReleasePath();
        $env = get('env', false);

        if (!$env) {
            throw new \RuntimeException('"--env" is now defined');
        }

        return ShellExec::run("$releasePath/app/console $command --env=$env");
    }

    /**
     * Check if command exist in bash.
     *
     * @param string $command
     * @return bool
     */
    public static function commandExist($command)
    {
        return Shell::commandExists($command);
    }

    /**
     * Generate file from template and save
     * @param $src
     * @param $dst
     * @param array $placeholders
     * @param string $mode
     * @param bool $remote
     * @return int
     */
    public static function generateFile($src, $dst, array $placeholders = [], $mode = null, $remote = false)
    {
        $helper = new GenerateFile($remote);
        $result = $helper->generateFile($src, $dst, $placeholders, $mode);
        if (output()->isVerbose() && $result) {
            output()->writeln('Generated file: ' . $dst);
        }
        return $result;
    }

    /**
     * Copy template files from dir
     *
     * @param $srcDir
     * @param $dstDir
     * @param array $placeholders
     * @param bool $remote
     * @return array
     * @throws \InvalidArgumentException
     */
    public static function generateFiles($srcDir, $dstDir, array $placeholders = [], $remote = false)
    {
        $helper = new GenerateFile($remote);
        $result = $helper->generateFiles($srcDir, $dstDir, $placeholders);
        if (output()->isDebug() && $result) {
            output()->writeln('Generated files: ');
            foreach ($result as $file) {
                output()->writeln($file);
            }
        }
        return $result;
    }

    /**
     * Update user crontab from file
     * @param string $filename Absolute path of new crontab
     * @return string|void
     */
    public static function updateCrontab($filename)
    {
        $helper = new Crontab();
        return $helper->update($filename);
    }

    /**
     * @param $name
     * @param $originalName
     * @return \Deployer\Task\TaskInterface
     */
    public static function aliasTask($name, $originalName)
    {
        $task = Deployer::get()->getTask($originalName);
        return Deployer::get()->addTask($name, $task);
    }

    public static function dirExists($dir, $isRelative = false)
    {
        if ($isRelative) {
            $dir = env()->getWorkingPath() . DIRECTORY_SEPARATOR . $dir;
        }
        return Shell::dirExists($dir);
    }

    public static function mkdir($dir, $isRelative = false)
    {
        if ($isRelative) {
            $dir = env()->getWorkingPath() . DIRECTORY_SEPARATOR . $dir;
        }
        return Shell::mkdir($dir);
    }
}
