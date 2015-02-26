<?php
use Symfony\Component\Console\Input\InputInterface;
use TheRat\SymDep\Helper\GenerateFile;

if (!function_exists('commandExist')) {
    /**
     * Check if command exist in bash.
     *
     * @param string $command
     * @return bool
     */
    function commandExist($command)
    {
        $res = run("if hash $command 2>/dev/null; then echo 'true'; fi");
        if ('true' === $res) {
            return true;
        } else {
            return false;
        }
    }
}

function runConsoleCommand($command)
{
    $releasePath = env()->getReleasePath();
    $env = get('env', false);

    if (!$env) {
        throw new \RuntimeException('"--env" is now defined');
    }

    return run("php $releasePath/app/console $command --env=$env --no-debug");
}

function getCurrentBranch(InputInterface $input = null)
{
    $branch = get('branch', false);
    if (!$branch) {
        $branch = $input->getOption('branch');
        if (!$branch) {
            $branch = trim(run('git rev-parse --abbrev-ref HEAD'));
        }
        set('branch', $branch);
    }
    return $branch;
}

/**
 * Generate file from template and save
 * @param $src
 * @param $dst
 * @param array $placeholders
 * @param null $mode
 * @return int
 * @throws \InvalidArgumentException
 */
function generateFile($src, $dst, array $placeholders = [], $mode = null)
{
    $helper = new GenerateFile();
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
 * @return array
 * @throws \InvalidArgumentException
 */
function generateFiles($srcDir, $dstDir, array $placeholders = [])
{
    $helper = new GenerateFile();
    $result = $helper->generateFiles($srcDir, $dstDir, $placeholders);
    if (output()->isVerbose() && $result) {
        output()->writeln('Generated files: ');
        foreach ($result as $file) {
            output()->writeln($file);
        }
    }
    return $result;
}
