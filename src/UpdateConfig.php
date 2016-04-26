<?php
namespace TheRat\SymDep;

/**
 * Class UpdateConfig
 * @package TheRat\SymDep
 */
class UpdateConfig
{
    /**
     * Update nginx config
     *
     * @param string $srcFilename
     * @param string $dstFilename
     */
    public static function updateNginx($srcFilename, $dstFilename)
    {
        if (!$srcFilename) {
            writeln('Env "nginx_src_filename" is not defined');

            return;
        }
        if (!FileHelper::fileExists($srcFilename)) {
            throw new \RuntimeException(
                sprintf('File nginx_src_filename:"%s" not found', $srcFilename)
            );
        }
        if (!$dstFilename) {
            writeln('Env "nginx_dst_filename" is not defined');

            return;
        }
        $backupDir = env('backup_dir', '');
        $backupDir = $backupDir ?: '{{deploy_path}}/backup/nginx';

        if (!FileHelper::dirExists($backupDir)) {
            run('mkdir -p '.$backupDir);
            !isVerbose() ?: writeln(sprintf('Backup dir "%s" created', $backupDir));
        }

        $backupFilename = sprintf('%s/nginx.%s', $backupDir, date('Y-m-d_H:i:s'));
        run(sprintf('cat %s > %s', $dstFilename, $backupFilename));

        $diff = run(
            sprintf('if ! diff -q %s %s > /dev/null 2>&1; then echo "true"; fi', $backupFilename, $srcFilename)
        )->toBool();

        if ($diff) {
            run(sprintf('cp "%s" "%s"', $srcFilename, $dstFilename));
            !isVerbose() ?: writeln(run(sprintf('cat %s', $dstFilename))->getOutput());
        } else {
            !isVerbose() ?: writeln('Nginx has no diff');
            run('rm '.$backupFilename);
        }
    }

    /**
     * Update user crontab
     *
     * @param string $srcFilename
     */
    public static function updateCrontab($srcFilename)
    {
        if (!$srcFilename) {
            writeln('Env "crontab_filename" is not defined');

            return;
        }
        if (!FileHelper::fileExists(env('crontab_filename'))) {
            throw new \RuntimeException(
                sprintf(
                    'File crontab_filename:"%s" not found',
                    $srcFilename
                )
            );
        }
        $sourceFilename = env('crontab_filename');
        $backupDir = env('backup_dir', '');
        $backupDir = $backupDir ?: '{{deploy_path}}/backup/crontab';

        if (!FileHelper::dirExists($backupDir)) {
            run('mkdir -p '.$backupDir);
            !isVerbose() ?: writeln(sprintf('Backup dir "%s" created', $backupDir));
        }

        $backupFilename = sprintf('%s/crontab.%s', $backupDir, date('Y-m-d_H:i:s'));
        if (run('if crontab -l > /dev/null 2>&1; then echo "true"; else echo \'false\'; fi')->toBool()) {
            run(sprintf('crontab -l > %s', $backupFilename));
        } else {
            run(sprintf('touch %s', $backupFilename));
        }

        $diff = run(
            sprintf('if ! diff -q %s %s > /dev/null 2>&1; then echo "true"; fi', $backupFilename, $sourceFilename)
        )->toBool();

        if ($diff) {
            run(sprintf('crontab "%s"', $sourceFilename));
            !isVerbose() ?: writeln(run('crontab -l')->getOutput());
        } else {
            !isVerbose() ?: writeln('Crontab has no diff');
            run('rm '.$backupFilename);
        }
    }
}
