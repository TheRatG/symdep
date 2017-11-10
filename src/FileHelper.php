<?php
namespace TheRat\SymDep;

use Deployer\Type\Result;
use Symfony\Component\Process\Exception\ProcessFailedException;
use function Deployer\isDebug;
use function Deployer\parse;
use function Deployer\run;
use function Deployer\within;
use function Deployer\writeln;
use function Deployer\upload;

/**
 * Class FileHelper
 *
 * @package TheRat\SymDep
 */
class FileHelper
{
    public static function copyFile($src, $dst)
    {
        $dstDir = dirname($dst);
        $result = false;
        if (!self::fileExists($dst)) {
            if (!self::dirExists($dstDir)) {
                run("mkdir -p \"$dstDir\"");
            }
            run("cp \"$src\" \"$dst\"");
            $result = true;
        }

        return $result;
    }

    /**
     * @param string $srcFilename
     * @param string $dstFilename
     * @param string $mode     Example +x, 0644
     * @param array  $copyOnce Regexp array
     * @return string
     */
    public static function generateFile($srcFilename, $dstFilename, $mode = null, array $copyOnce = [])
    {
        $mode = !is_null($mode) ? (string)$mode : null;

        $copyOnce = array_map(
            function ($value) {
                return parse($value);
            },
            $copyOnce
        );

        if (in_array($srcFilename, $copyOnce) && self::fileExists($dstFilename)) {
            !isDebug() ?: writeln(sprintf('File "%s" skipped, because is in copyOnce list', $srcFilename));

            return '';
        }

        $dstDir = dirname($dstFilename);
        if (!self::fileExists($srcFilename)) {
            throw new \RuntimeException(
                parse('Src file "'.$srcFilename.'" does not exists')
            );
        }
        $command = sprintf('if [ -d "%s" ]; then mkdir -p "%s"; fi', $dstDir, $dstDir);
        run($command);

        $dstDir = dirname($dstFilename);
        if (!self::dirExists($dstDir)) {
            run(sprintf('mkdir -p "%s"', $dstDir));
        }

        $content = run(sprintf('cat "%s"', $srcFilename));
        $content = parse($content);
        $command = <<<DOCHERE
cat > "$dstFilename" <<'_EOF'
$content
_EOF
DOCHERE;
        run($command);

        if (is_null($mode)) {
            try {
                $command = sprintf('stat -c "%%a" % s', $srcFilename);
                $mode = trim(run($command));
            } catch (ProcessFailedException $e) {
                $command = sprintf('stat -f "%%A" % s', $srcFilename);
                $mode = trim(run($command));
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
        $templateFiles = explode("\n", run($command));

        $result = [];
        foreach ($templateFiles as $src) {
            $name = str_replace($srcDir, '', $src);
            $dst = sprintf('%s%s', $dstDir, $name);
            $res = self::generateFile($src, $dst, null, $copyOnce);
            if ($res) {
                $result[] = $dst;
            }
        }

        return $result;
    }

    /**
     * @param string $dir
     * @param string $workingPath
     * @return bool
     */
    public static function dirExists($dir, $workingPath = null)
    {
        return (bool)self::runWithin("if [ -d \"$dir\" ]; then echo 1; fi", $workingPath);
    }

    /**
     * @param string $filename
     * @param string $workingPath
     * @return bool
     */
    public static function fileExists($filename, $workingPath = null)
    {
        return (bool)self::runWithin("if [ -f \"$filename\" ]; then echo 1; fi", $workingPath);
    }

    /**
     * @param string $filename
     * @param string $workingPath
     * @return bool
     */
    public static function isWritable($filename, $workingPath = null)
    {
        $cmd = sprintf('if [ -w "%s" ]; then echo true; fi', $filename);

        return (bool)self::runWithin($cmd, $workingPath);
    }

    /**
     * @param string $command
     * @param string $workingPath
     * @return Result
     */
    public static function runWithin($command, $workingPath = null)
    {
        $result = null;
        if (is_null($workingPath)) {
            $result = run($command);
        } else {
            within(
                $workingPath,
                function () use ($command, &$result) {
                    $result = run($command);
                }
            );
        }

        return $result;
    }
}
