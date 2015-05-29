<?php
namespace TheRat\SymDep\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class InstallDeployerCommand extends Command
{
    protected function configure()
    {
        $this
            ->setName('symdep:install-deployer')
            ->setDescription('Download or update deployer.phar');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $dir = dirname(dirname(__DIR__));
        $url = 'http://deployer.org/deployer.phar';
        $dstFile = $dir . '/deployer.phar';

        if (!file_exists($dstFile)) {
            $fp = fopen($dstFile, 'w+');//This is the file where we save the    information
            $ch = curl_init($url);//Here is the file we are downloading, replace spaces with %20
            curl_setopt($ch, CURLOPT_TIMEOUT, 50);
            curl_setopt($ch, CURLOPT_FILE, $fp); // write curl response to file
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_exec($ch); // get curl response
            curl_close($ch);
            fclose($fp);

            $cmd = "chmod +x $dstFile";
        } else {
            $cmd = "php $dstFile self-update";
        }

        try {
            $process = new Process($cmd);
            $process->mustRun();

            if (OutputInterface::VERBOSITY_NORMAL < $output->getVerbosity()) {
                $output->writeln($process->getOutput());
            }
        } catch (ProcessFailedException $e) {
            $output->writeln($e->getMessage());
        }
    }
}
