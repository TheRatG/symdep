<?php
namespace TheRat\SymDep;

/**
 * @param $srcFilename
 * @param $dstFilename
 * @param string $mode Example +x, 0644
 * @param bool $locally
 */
function generateFile($srcFilename, $dstFilename, $mode = null, $locally = false)
{
    $mode = !is_null($mode) ? (string)$mode : null;

    $dstDir = dirname($dstFilename);
    if (!runCommand("if [ -f $(echo $srcFilename) ]; then echo true; fi", $locally)->toBool()) {
        throw new \RuntimeException("Src file '$srcFilename' does not exists");
    }
    $command = "if [ -d \"$dstDir\" ]; then mkdir -p \"$dstDir\"; fi";
    runCommand($command, $locally);

    $content = runCommand("cat \"$srcFilename\"", $locally);
    $content = env()->parse($content);
    $command = <<<DOCHERE
cat > "$dstFilename" <<'_EOF'
$content
_EOF
DOCHERE;
    runCommand($command, $locally);

    $command = "chmod $mode $dstFilename";
    if (is_null($mode)) {
        $command = "chmod --reference $srcFilename $dstFilename";
    }
    runCommand($command, $locally);

    return $dstFilename;
}

function generateFiles($srcDir, $dstDir, $locally)
{
    $srcDir = rtrim($srcDir, '/');
    $dstDir = rtrim($dstDir, '/');

    $command = "find $srcDir -type f";
    $templateFiles = runCommand($command, $locally)->toArray();

    $result = [];
    foreach ($templateFiles as $src) {
        $name = str_replace($srcDir, '', $src);
        $dst = sprintf('%s%s', $dstDir, $name);
        $res = generateFile($src, $dst, null, $locally);
        if ($res) {
            $result[] = $dst;
        }
    }
    return $result;
}

/**
 * @param $command
 * @param bool $locally
 * @return \Deployer\Type\Result|void
 */
function runCommand($command, $locally = false)
{
    if ($locally) {
        return runLocally($command);
    } else {
        return run($command);
    }
}

function dirExists($dir, $locally = false)
{
    return runCommand("if [ -d \"$dir\" ]; then echo true; fi", $locally)->toBool();
}

function fileExists($filename, $locally = false)
{
    return runCommand("if [ -f \"$filename\" ]; then echo true; fi", $locally)->toBool();
}
