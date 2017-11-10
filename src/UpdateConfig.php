<?php

namespace TheRat\SymDep;

use function Deployer\isVerbose;
use function Deployer\run;
use function Deployer\writeln;

/**
 * Class UpdateConfig
 *
 * @package TheRat\SymDep
 */
class UpdateConfig
{
    /**
     * Update nginx config
     *
     * @param string $srcFilename
     * @param string $dstFilename
     * @param string $backupDir
     *
     * @return bool
     */
    public static function updateNginx($srcFilename, $dstFilename, $backupDir = null)
    {
        return self::updateFile($srcFilename, $dstFilename, 'nginx', $backupDir);
    }

    /**
     * @param string $srcFilename
     * @param string $dstFilename
     * @param string $backupName
     * @param string $backupDir
     * @return bool
     */
    public static function updateFile($srcFilename, $dstFilename, $backupName, $backupDir = null)
    {
        if (empty($srcFilename)) {
            throw new \InvalidArgumentException('Invalid argument $srcFilename, must be not empty');
        }
        if (empty($dstFilename)) {
            throw new \InvalidArgumentException('Invalid argument $dstFilename, must be not empty');
        }
        if (!FileHelper::fileExists($srcFilename)) {
            throw new \RuntimeException(
                sprintf('Source file "%s" not found', $srcFilename)
            );
        }

        $backupDir = $backupDir ?: '{{deploy_path}}/backup/'.$backupName;

        if (!FileHelper::dirExists($backupDir)) {
            run('mkdir -p '.$backupDir);
            !isVerbose() ?: writeln(sprintf('Backup dir "%s" created', $backupDir));
        }

        $diff = true;
        $backupFilename = '';
        if (FileHelper::fileExists($dstFilename)) {
            $backupFilename = sprintf('%s/%s.%s', $backupDir, $backupName, date('Y-m-d_H:i:s'));
            run(sprintf('cat %s > %s', $dstFilename, $backupFilename));

            $diff = (bool)run(
                sprintf('if ! diff -q %s %s > /dev/null 2>&1; then echo 1; fi', $backupFilename, $srcFilename)
            );
        }

        $result = false;
        if ($diff) {
            run(sprintf('cp "%s" "%s"', $srcFilename, $dstFilename));
            !isVerbose() ?: writeln(run(sprintf('cat %s', $dstFilename)));
            !isVerbose() ?: writeln(sprintf('File %s updated', $dstFilename));
            $result = true;
        } else {
            !isVerbose() ?: writeln('File has no diff');
            if ($backupFilename) {
                run('rm '.$backupFilename);
            }
        }

        return $result;
    }

    /**
     * Update user crontab
     *
     * @param string $sourceFilename
     * @param null   $backupDir
     *
     * @return bool
     */
    public static function updateCrontab($sourceFilename, $backupDir = null)
    {
        if (empty($sourceFilename)) {
            throw new \InvalidArgumentException('Invalid argument $srcFilename, must be not empty');
        }

        if (!FileHelper::fileExists($sourceFilename)) {
            throw new \RuntimeException(
                sprintf(
                    'File crontab_filename:"%s" not found',
                    $sourceFilename
                )
            );
        }
        $backupDir = $backupDir ?: '{{deploy_path}}/backup/crontab';

        if (!FileHelper::dirExists($backupDir)) {
            run('mkdir -p '.$backupDir);
            !isVerbose() ?: writeln(sprintf('Backup dir "%s" created', $backupDir));
        }

        $backupFilename = sprintf('%s/crontab.%s', $backupDir, date('Y-m-d_H:i:s'));
        if ((bool)run('if crontab -l > /dev/null 2>&1; then echo 1; else echo 0; fi')) {
            run(sprintf('crontab -l > %s', $backupFilename));
        } else {
            run(sprintf('touch %s', $backupFilename));
        }

        $diff = (bool)run(
            sprintf('if ! diff -q %s %s > /dev/null 2>&1; then echo 1; fi', $backupFilename, $sourceFilename)
        );

        $result = false;
        if ($diff) {
            run(sprintf('crontab "%s"', $sourceFilename));
            !isVerbose() ?: writeln(run('crontab -l'));

            $result = true;
        } else {
            !isVerbose() ?: writeln('Crontab has no diff');
            run('rm '.$backupFilename);
        }

        return $result;
    }
}
