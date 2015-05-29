<?php
namespace TheRat\SymDep\Command;

use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

class SelfUpdateCommand extends Command
{
    /** @var Filesystem */
    private $fs;

    /** @var OutputInterface */
    private $output;

    private $tempDir;

    /** @var string  the URL where the latest installer version can be downloaded */
    private $remoteInstallerFile;

    /** @var string the filepath of the installer currently installed in the local machine */
    private $currentInstallerFile;

    /** @var string the filepath of the new installer downloaded to replace the current installer */
    private $newInstallerFile;

    /** @var string the filepath of the backup of the current installer in case a rollback is performed */
    private $currentInstallerBackupFile;

    /** @var bool flag which indicates that, in case of a rollback, it's safe to restore the installer backup because it corresponds to the most recent version */
    private $restorePreviousInstaller;


    protected function configure()
    {
        $this
            ->setName('symdep:self-update')
            ->setAliases(['symdep:selfupdate'])
            ->setDescription('Update the installer to the latest version.')
            ->setHelp('The <info>%command.name%</info> command updates the installer to the latest available version.');
    }

    /**
     * The self-update command is only available when using the installer via the PHAR file.
     */
    public function isEnabled()
    {
        return 'phar://' === substr(__DIR__, 0, 7);
    }

    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $this->fs = new Filesystem();
        $this->output = $output;

        $this->remoteInstallerFile = 'https://raw.githubusercontent.com/TheRatG/symdep/master/symdep.phar';
        $this->currentInstallerFile = realpath($_SERVER['argv'][0]) ?: $_SERVER['argv'][0];
        $this->tempDir = sys_get_temp_dir();
        $this->currentInstallerBackupFile = basename($this->currentInstallerFile, '.phar') . '-backup.phar';
        $this->newInstallerFile = $this->tempDir . '/' . basename($this->currentInstallerFile, '.phar') . '-temp.phar';
        $this->restorePreviousInstaller = false;
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if ($this->installerIsUpdated()) {
            return;
        }

        try {
            $this
                ->downloadNewVersion()
                ->checkNewVersionIsValid()
                ->backupCurrentVersion()
                ->replaceCurrentVersionbyNewVersion()
                ->cleanUp();
        } catch (IOException $e) {
            throw new \RuntimeException(sprintf(
                "The installer couldn't be updated, probably because of a permissions issue.\n" .
                "Try to execute the command again with super user privileges:\n" .
                "  sudo %s\n",
                $this->getExecutedCommand()
            ));

            if (OutputInterface::VERBOSITY_VERY_VERBOSE === $output->getVerbosity()) {
                echo $e->getMessage();
            }
        } catch (\Exception $e) {
            $this->rollback();

            if (OutputInterface::VERBOSITY_VERY_VERBOSE === $output->getVerbosity()) {
                echo $e->getMessage();
            }
        }
    }

    private function installerIsUpdated()
    {
        $isUpdated = false;
        $localVersion = $this->getApplication()->getVersion();

        if (false === $remoteVersion = @file_get_contents('https://raw.githubusercontent.com/TheRatG/symdep/master/version')) {
            throw new \RuntimeException('The new version of the Symfony Installer couldn\'t be downloaded from the server.');
        }

        if ($localVersion === $remoteVersion) {
            $this->output->writeln('<info>Symfony Installer is already up to date.</info>');
            $isUpdated = true;
        } else {
            $this->output->writeln(sprintf('// <info>updating</info> Symfony Installer to <comment>%s</comment> version', $remoteVersion));
        }

        return $isUpdated;
    }

    private function downloadNewVersion()
    {
        // check for permissions in local filesystem before start downloading files
        if (!is_writable($this->currentInstallerFile)) {
            throw new \RuntimeException('Symfony Installer update failed: the "' . $this->currentInstallerFile . '" file could not be written');
        }

        if (!is_writable($this->tempDir)) {
            throw new \RuntimeException('Symfony Installer update failed: the "' . $this->tempDir . '" directory used to download files temporarily could not be written');
        }

        if (false === $newInstaller = @file_get_contents($this->remoteInstallerFile)) {
            throw new \RuntimeException('The new version of the Symfony Installer couldn\'t be downloaded from the server.');
        }

        $newInstallerPermissions = $this->currentInstallerFile ? fileperms($this->currentInstallerFile) : 0777 & ~umask();
        $this->fs->dumpFile($this->newInstallerFile, $newInstaller, $newInstallerPermissions);

        return $this;
    }

    private function checkNewVersionIsValid()
    {
        // creating a Phar instance for an existing file is not allowed
        // when the Phar extension is in readonly mode
        if (!ini_get('phar.readonly')) {
            // test the phar validity
            $phar = new \Phar($this->newInstallerFile);

            // free the variable to unlock the file
            unset($phar);
        }

        return $this;
    }

    private function backupCurrentVersion()
    {
        $this->fs->copy($this->currentInstallerFile, $this->currentInstallerBackupFile, true);
        $this->restorePreviousInstaller = true;

        return $this;
    }

    private function replaceCurrentVersionbyNewVersion()
    {
        $this->fs->copy($this->newInstallerFile, $this->currentInstallerFile, true);

        return $this;
    }

    private function cleanUp()
    {
        $this->fs->remove([$this->currentInstallerBackupFile, $this->newInstallerFile]);
    }

    private function rollback()
    {
        $this->output->writeln([
            '',
            'There was an error while updating the installer.',
            'The previous Symfony Installer version has been restored.',
            '',
        ]);

        $this->fs->remove($this->newInstallerFile);

        if ($this->restorePreviousInstaller) {
            $this->fs->copy($this->currentInstallerBackupFile, $this->currentInstallerFile, true);
        }
    }
}
