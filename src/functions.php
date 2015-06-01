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
    if (!runLocally("if [ -f $(echo $srcFilename) ]; then echo true; fi")->toBool()) {
        throw new \RuntimeException("Src file '$srcFilename' does not exists");
    }
    $command = "if [ -d \"$dstDir\" ]; then mkdir -p \"$dstDir\"; fi";
    runCommand($command, $locally);

    $content = runLocally("cat \"$srcFilename\"");
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
}

function runCommand($command, $locally = false)
{
    if ($locally) {
        return runLocally($command);
    } else {
        return run($command);
    }
}
