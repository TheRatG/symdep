<?php
namespace TheRat\SymDep;

use Symfony\Component\Process\Exception\ProcessFailedException;

/**
 * Class FileHelper
 *
 * @package TheRat\SymDep
 */
class FileHelper
{
    /**
     * @param string $srcFilename
     * @param string $dstFilename
     * @param string $mode
     * @param array  $copyOnce
     * @return string
     */
    public static function generateFile($srcFilename, $dstFilename, $mode = null, array $copyOnce = [])
    {
        $mode = !is_null($mode) ? (string) $mode : null;

        $copyOnce = array_map(
            function ($value) {
                return env()->parse($value);
            },
            $copyOnce
        );

        if (in_array($srcFilename, $copyOnce) && fileExists($dstFilename)) {
            !isDebug() ?: writeln(sprintf('File "%s" skipped, because is in copyOnce list', $srcFilename));

            return '';
        }

        $dstDir = dirname($dstFilename);
        if (!self::fileExists($srcFilename)) {
            throw new \RuntimeException(
                env()->parse(
                    'Src file "'.$srcFilename.'" does not exists'
                )
            );
        }
        $command = sprintf('if [ -d "%s" ]; then mkdir -p "%s"; fi', $dstDir, $dstDir);
        run($command);

        $dstDir = dirname($dstFilename);
        if (!self::dirExists($dstDir)) {
            run(sprintf('mkdir -p "%s"', $dstDir));
        }

        $content = run(sprintf('cat "%s"', $srcFilename));
        $content = env()->parse($content);
        $command = <<<DOCHERE
cat > "$dstFilename" <<'_EOF'
$content
_EOF
DOCHERE;
        run($command);

        if (is_null($mode)) {
            try {
                $command = sprintf('stat -c "%%a" % s', $srcFilename);
                $mode = trim(run($command)->toString());
            } catch (ProcessFailedException $e) {
                $command = sprintf('stat -f "%%A" % s', $srcFilename);
                $mode = trim(run($command)->toString());
            }
        }

        $command = sprintf('chmod %s "%s"', $mode, $dstFilename);
        run($command);

        return $dstFilename;
    }

    /**
     * @param string $srcDir
     * @param string $dstDir
     * @param array  $copyOnce
     * @return array
     */
    public static function generateFiles($srcDir, $dstDir, array $copyOnce = [])
    {
        $srcDir = rtrim($srcDir, ' / ');
        $dstDir = rtrim($dstDir, ' / ');

        $command = sprintf('find "%s" -type f', $srcDir);
        $templateFiles = run($command)->toArray();

        $result = [];
        foreach ($templateFiles as $src) {
            $name = str_replace($srcDir, '', $src);
            $dst = sprintf('%s%s', $dstDir, $name);
            $res = self::generateFile($src, $dst, null);
            if ($res) {
                $result[] = $dst;
            }
        }

        return $result;
    }

    /**
     * @param string $dir
     * @return mixed
     */
    public static function dirExists($dir)
    {
        $cmd = sprintf('if [ -d "%s" ]; then echo true; fi', $dir);

        return run($cmd)->toBool();
    }

    /**
     * @param string $filename
     * @return mixed
     */
    public static function fileExists($filename)
    {
        $cmd = sprintf('if [ -f "%s" ]; then echo true; fi', $filename);

        return run($cmd)->toBool();
    }
}
