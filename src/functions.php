<?php
use Symfony\Component\Console\Input\InputInterface;
use TheRat\SymDep\Helper\GenerateFile;
use TheRat\SymDep\Helper\RunHelper;

function getCurrentBranch(InputInterface $input = null)
{
    $branch = get('branch', false);
    if (!$branch) {
        $branch = $input->getOption('branch');
        if (!$branch) {
            $branch = trim(RunHelper::execLocally('git rev-parse --abbrev-ref HEAD', false));
        }
        set('branch', $branch);
    }
    return $branch;
}

function runConsoleCommand($command)
{
    $releasePath = env()->getReleasePath();
    $env = get('env', false);

    if (!$env) {
        throw new \RuntimeException('"--env" is now defined');
    }

    return RunHelper::exec("$releasePath/app/console $command --env=$env");
}

/**
 * Check if command exist in bash.
 *
 * @param string $command
 * @return bool
 */
function programExist($command)
{
    $res = RunHelper::exec("if hash $command 2>/dev/null; then echo 'true'; fi", true);
    if ('true' === trim($res)) {
        return true;
    } else {
        return false;
    }
}

/**
 * Generate file from template and save
 * @param $src
 * @param $dst
 * @param array $placeholders
 * @param null $mode
 * @param bool $remote
 * @return int
 */
function generateFile($src, $dst, array $placeholders = [], $mode = null, $remote = false)
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
function generateFiles($srcDir, $dstDir, array $placeholders = [], $remote = false)
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
function updateCrontab($filename)
{
    $helper = new \TheRat\SymDep\Helper\Crontab();
    return $helper->update($filename);
}
