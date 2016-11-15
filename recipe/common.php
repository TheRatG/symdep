<?php
use TheRat\SymDep\FileHelper;
use TheRat\SymDep\UpdateConfig;

task(
    'properties',
    function () {

        // Keep releases
        set('keep_releases', 5);

        // Composer install --no-dev
        env('composer_no_dev', true);

        // Symfony shared dirs
        set('shared_dirs', ['app/logs', 'web/uploads', 'app/sessions']);

        // Symfony writable dirs
        set('writable_dirs', ['app/cache', 'app/logs', 'web/uploads']);

        // Assets
        set('assets', ['web/css', 'web/images', 'web/js']);

        // Auto migrate
        set('auto_migrate', true);

        //Doctrine cache clear
        set('doctrine_cache_clear', true);

        set('bin_dir', 'app');

        set('var_dir', 'app');

        //console
        env('symfony_console', '{{release_path}}/'.trim(get('bin_dir'), '/').'/console');

        // Deploy branch
        $branch = input()->getArgument('branch');
        $localBranch = runLocally('git rev-parse --abbrev-ref HEAD')->toString();
        if (!$branch) {
            $branch = $localBranch;
        }

        env('local_branch', $localBranch);
        env('branch', $branch);
        set('branch', $branch);

        env('lock_keep', 15);
        env('lock_dir', '');
        env('lock_wait', input()->getOption('lock-wait'));
        env('no_debug', false); //symfony console option

        /**
         * Custom bins.
         */
        if (!env()->has('bin/php')) {
            env('bin/php', run('which php')->toString());
        }
        if (!env()->has('bin/git')) {
            env('bin/git', run('which git')->toString());
        }
    }
)->desc('1. Prepare environment properties');

task(
    'install-before',
    function () {

    }
)->desc('2. Before install');

task(
    'install',
    function () {

    }
)->desc('3. Deploy and prepare files');

task(
    'install-after',
    function () {

    }
)->desc('4. After install');

task(
    'configure-before',
    function () {

    }
)->desc('5. Before configure');

task(
    'configure',
    function () {

    }
)->desc('6. Run necessary scripts for project');

task(
    'configure-after',
    function () {

    }
)->desc('7. After configure');

task(
    'link-before',
    function () {

    }
)->desc('8. Before link');

task(
    'link',
    function () {

    }
)->desc('9. Change symlinks');

task(
    'link-after',
    function () {

    }
)->desc('10. after link');

/**
 * Rollback to previous release.
 */
task(
    'rollback',
    function () {
        $releases = env('releases_list');

        if (isset($releases[1])) {
            $releaseDir = "{{deploy_path}}/releases/{$releases[1]}";

            // Symlink to old release.
            run("cd {{deploy_path}} && ln -nfs $releaseDir current");

            // Remove release
            run("rm -rf {{deploy_path}}/releases/{$releases[0]}");

            if (isVerbose()) {
                writeln("Rollback to `{$releases[1]}` release was successful.");
            }
        } else {
            writeln("<comment>No more releases you can revert to.</comment>");
        }
    }
)->desc('Rollback to previous release');

task(
    'check_connection',
    function () {
        \Deployer\Task\Context::get()->getServer()->connect();

        // Check if shell is POSIX-compliant
        try {
            cd(''); // To run command as raw.
            $result = run('echo $0')->toString();
            if ($result == 'stdin: is not a tty') {
                throw new RuntimeException(
                    "Looks like ssh inside another ssh.\n".
                    "Help: http://goo.gl/gsdLt9"
                );
            }
        } catch (\RuntimeException $e) {
            $errorMessage = [
                "Shell on your server is not POSIX-compliant. Please change to sh, bash or similar.",
                "Usually, you can change your shell to bash by running: chsh -s /bin/bash",
            ];
            writeln('<error>'.$errorMessage.'</error>');
            throw $e;
        }
    }
);

/**
 * Create symlinks for shared directories and files.
 */
task(
    'shared',
    function () {
        $sharedPath = "{{deploy_path}}/shared";

        foreach (get('shared_dirs') as $dir) {
            // Remove from source.
            run("if [ -d $(echo {{release_path}}/$dir) ]; then rm -rf {{release_path}}/$dir; fi");
            // Create shared dir if it does not exist.
            run("mkdir -p $sharedPath/$dir");
            // Create path to shared dir in release dir if it does not exist.
            // (symlink will not create the path and will fail otherwise)
            run("mkdir -p `dirname {{release_path}}/$dir`");
            // Symlink shared dir to release dir
            run("ln -nfs $sharedPath/$dir {{release_path}}/$dir");
        }

        $sharedFiles = get('shared_files');
        $sharedFiles[] = 'app/config/_secret.yml';
        foreach ($sharedFiles as $file) {
            $dirname = dirname($file);
            // Remove from source.
            run("if [ -f $(echo {{release_path}}/$file) ]; then rm -rf {{release_path}}/$file; fi");
            // Ensure dir is available in release
            run("if [ ! -d $(echo {{release_path}}/$dirname) ]; then mkdir -p {{release_path}}/$dirname;fi");
            // Create dir of shared file
            run("mkdir -p $sharedPath/".$dirname);
            // Touch shared
            run("touch $sharedPath/$file");
            // Symlink shared dir to release dir
            run("ln -nfs $sharedPath/$file {{release_path}}/$file");
        }

        if (!FileHelper::fileExists('{{release_path}}/app/config/_secret.yml')) {
            run('touch {{release_path}}/app/config/_secret.yml');
        }
    }
)->desc('Creating symlinks for shared files');

/**
 * Create cache dir
 */
task(
    'create_cache_dir',
    function () {
        // Set cache dir
        env('cache_dir', '{{release_path}}/'.trim(get('var_dir'), '/').'/cache');

        // Remove cache dir if it exist
        run('if [ -f "{{cache_dir}}" ]; then rm -rf {{cache_dir}}; fi');

        // Create cache dir
        run('mkdir -p {{cache_dir}}');

        // Set rights
        run("chmod -R g+w {{cache_dir}}");
    }
)->desc('Create cache dir');

/**
 * Make writable dirs.
 */
task(
    'writable',
    function () {
        $dirs = join(' ', get('writable_dirs'));

        if (!empty($dirs)) {
            run("cd {{release_path}} && chmod 777 $dirs");
        }

    }
)->desc('Make writable dirs');

/**
 * Normalize asset timestamps
 */
task(
    'assets',
    function () {
        $assets = array_map(
            function ($asset) {
                return "{{release_path}}/$asset";
            },
            get('assets')
        );

        $time = date('Ymdhi.s');

        foreach ($assets as $dir) {
            if (FileHelper::dirExists($dir)) {
                run("find $dir -exec touch -t $time {} ';' &> /dev/null || true");
            }
        }
    }
)->desc('Normalize asset timestamps');

/**
 * Installing vendors tasks.
 */
task(
    'vendors',
    function () {
        if (run("if hash composer 2>/dev/null; then echo 'true'; fi")->toBool()) {
            $composer = run('which composer')->toString();
        } else {
            run('cd {{release_path}} && curl -sS https://getcomposer.org/installer | {{bin/php}}');
            $composer = 'composer.phar';
        }

        $options = '--optimize-autoloader --no-progress --no-interaction';
        $options .= env('composer_no_dev') ? ' --no-dev' : '';
        $options .= 'dev' == env('env') ? ' --prefer-source' : ' --prefer-dist';

        run("cd {{release_path}} && {{env_vars}} {{bin/php}} $composer install $options");
        run("cd {{release_path}} && {{env_vars}} {{bin/php}} $composer dump-autoload");

    }
)->desc('Installing vendors');

/**
 * Dump all assets to the filesystem
 */
task(
    'assetic:dump',
    function () {
        run('cd {{release_path}} && {{symfony_console}} assets:install --symlink --env={{env}}');
        run('cd {{release_path}} && {{symfony_console}} assetic:dump --env={{env}}');
    }
)->desc('Dump assets');


/**
 * Warm up cache
 */
task(
    'cache:warmup',
    function () {

        $noDebug = env('no_debug') ? ' --no-debug' : '';
        run('{{symfony_console}} cache:warmup --env={{env}}'.$noDebug);

    }
)->desc('Warm up cache');

/**
 * Warm up cache
 */
task(
    'cache:clear',
    function () {

        $noDebug = env('no_debug') ? ' --no-debug' : '';
        run('{{symfony_console}} cache:clear --env={{env}}'.$noDebug);

    }
)->desc('Clear cache');

/**
 * Migrate database
 */
task(
    'database:migrate',
    function () {
        if (get('auto_migrate')) {
            $noDebug = env('no_debug') ? ' --no-debug' : '';
            run('{{symfony_console}} doctrine:migrations:migrate --env={{env}} --no-interaction'.$noDebug);
        }
    }
)->desc('Migrate database');

/**
 * Doctrine cache clear database
 */
task(
    'database:cache-clear',
    function () {
        if (get('doctrine_cache_clear')) {
            $noDebug = env('no_debug') ? ' --no-debug' : '';
            run('{{symfony_console}} doctrine:cache:clear-metadata --env={{env}}'.$noDebug);
            run('{{symfony_console}} doctrine:cache:clear-query --env={{env}}'.$noDebug);
            run('{{symfony_console}} doctrine:cache:clear-result --env={{env}}'.$noDebug);
        }
    }
)->desc('Doctrine cache clear');

task(
    'lock',
    function () {
        $lockWait = env('lock_wait');
        $filename = env('lock_dir').'/symdep.lock';
        $locker = new \TheRat\SymDep\Locker($filename, env('lock_keep'));
        $needLock = true;

        if ($locker->isLocked()) {
            if ($lockWait) {
                writeln($locker->__toString());
                $needLock = askConfirmation('Force deploy');
            } else {
                $needLock = false;
            }
        }

        if ($needLock) {
            $locker->lock(
                [
                    'date' => trim(run('date -u')->toString()),
                    'user' => trim(runLocally('whoami')->toString()),
                    'server' => trim(runLocally('uname -a')->toString()),
                ]
            );
            if (isVerbose()) {
                writeln(sprintf('Create lock file "%s"', $filename));
            }
        } else {
            throw new \RuntimeException('Deploy process locked');
        }
    }
);

task(
    'unlock',
    function () {
        $filename = env('lock_dir').'/symdep.lock';
        $locker = new \TheRat\SymDep\Locker($filename, env('lock_keep'));
        $locker->unlock();
    }
);

/**
 * Delete useless branches, which no in remote repository
 */
task(
    'drop-branches',
    function () {
        if ('test' != env('env_real')) {
            throw new \RuntimeException('This command only for "test" build type');
        }

        $path = env('deploy_path').'/releases';
        $localBranches = run("ls $path")->toArray();

        $remoteBranches = run("cd $path/master && {{bin/git}} branch -r")->toArray();
        array_walk(
            $remoteBranches,
            function (&$item) {
                $item = trim($item);
                $item = substr($item, strpos($item, '/') + 1);
                $item = explode(' ', $item)[0];
                $item = strtolower($item);
            }
        );
        $diff = array_diff($localBranches, $remoteBranches);

        if (isVerbose()) {
            writeln(sprintf('<info>Local dir: %s</info>', implode(', ', $localBranches)));
            writeln(sprintf('<info>Remote branches: %s</info>', implode(', ', $remoteBranches)));
            writeln(
                sprintf(
                    '<comment>Dir for delete: %s</comment>',
                    !empty($diff) ? implode(', ', $diff) : 'none'
                )
            );
        }

        foreach ($diff as $deleteDir) {
            $full = "$path/$deleteDir";
            if (FileHelper::dirExists($full)) {
                $cmd = sprintf('rm -rf %s', escapeshellarg($full));
                if (isVerbose() && askConfirmation("Do you want delete: $full")) {
                    run($cmd);
                } else {
                    run($cmd);
                }
            }
        }
    }
);

task(
    'release-info-before',
    function () {
        if (FileHelper::dirExists(env()->parse('{{deploy_path}}/current'))) {
            $releaseInfo = new \TheRat\SymDep\ReleaseInfo();
            $releaseInfo->run();
        }
    }
)->desc('Release info');

task(
    'release-info-after',
    function () {
        if (FileHelper::dirExists(env()->parse('{{deploy_path}}/current'))) {
            $releaseInfo = new \TheRat\SymDep\ReleaseInfo();
            $releaseInfo->showIssues();
        }
    }
)->desc('Release info');

task(
    'crontab',
    function () {
        if (!env('crontab_filename')) {
            writeln('Env "crontab_filename" is not defined');

            return;
        }
        if (!FileHelper::fileExists(env('crontab_filename'))) {
            throw new \RuntimeException(
                sprintf(
                    'File crontab_filename:"%s" not found',
                    env()->parse('{{crontab_filename}}')
                )
            );
        }
        $sourceFilename = env('crontab_filename');
        $backupDir = env('backup_dir', '');
        $backupDir = $backupDir ?: '{{deploy_path}}/backup';

        UpdateConfig::updateCrontab($sourceFilename, $backupDir);
    }
);

task(
    'nginx',
    function () {
        if (!env('nginx_src_filename')) {
            writeln('Env "nginx_src_filename" is not defined');

            return;
        }
        if (!FileHelper::fileExists(env('nginx_src_filename'))) {
            throw new \RuntimeException(
                sprintf(
                    'File nginx_src_filename:"%s" not found',
                    env()->parse('{{nginx_src_filename}}')
                )
            );
        }
        if (!env('nginx_dst_filename')) {
            writeln('Env "nginx_dst_filename" is not defined');

            return;
        }
        $srcFilename = env('nginx_src_filename');
        $dstFilename = env('nginx_dst_filename');
        $backupDir = env('backup_dir', '');
        $backupDir = $backupDir ?: '{{deploy_path}}/backup';

        UpdateConfig::updateNginx($srcFilename, $dstFilename, $backupDir);
    }
);
