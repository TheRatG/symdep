<?php
namespace TheRat\SymDep;

const BUILD_TYPE_DEV = 'dev';
const BUILD_TYPE_TEST = 'test';
const BUILD_TYPE_PROD = 'prod';
const BUILD_TYPE_UNIT_TEST = 'unit_test';

function getBuildType()
{
    $options = getopt('::', ['build-type::']);
    $result = BUILD_TYPE_DEV;
    if (array_key_exists('build-type', $options)) {
        $firstLetter = strtolower($options['build-type'])[0];
        $map = [
            'd' => BUILD_TYPE_DEV,
            'u' => BUILD_TYPE_UNIT_TEST,
            't' => BUILD_TYPE_TEST,
            'p' => BUILD_TYPE_PROD,
        ];
        if (array_key_exists($firstLetter, $map)) {
            $result = $map[$firstLetter];
        } else {
            throw new \RuntimeException('Invalid strategy value, must be D | T | P');
        }
    }

    return $result;
}

/**
 * @param $srcFilename
 * @param $dstFilename
 * @param string $mode Example +x, 0644
 */
function generateFile($srcFilename, $dstFilename, $mode = null)
{
    $mode = !is_null($mode) ? (string)$mode : null;

    $dstDir = dirname($dstFilename);
    if (!fileExists($srcFilename)) {
        throw new \RuntimeException(env()->parse("Src file '$srcFilename' does not exists"));
    }
    $command = "if [ -d \"$dstDir\" ]; then mkdir -p \"$dstDir\"; fi";
    run($command);

    $dstDir = dirname($dstFilename);
    if (!dirExists($dstDir)) {
        run("mkdir -p \"$dstDir\"");
    }

    $content = run("cat \"$srcFilename\"");
    $content = env()->parse($content);
    $command = <<<DOCHERE
cat > "$dstFilename" <<'_EOF'
$content
_EOF
DOCHERE;
    run($command);

    if (is_null($mode)) {
        try {
            $command = "stat -c \"%a\" $srcFilename";
            $mode = trim(run($command)->toString());
        } catch (\Symfony\Component\Process\Exception\ProcessFailedException $e) {
            $command = "stat -f \"%A\" $srcFilename";
            $mode = trim(run($command)->toString());
        }
    }

    $command = "chmod $mode $dstFilename";
    run($command);

    return $dstFilename;
}

function generateFiles($srcDir, $dstDir)
{
    $srcDir = rtrim($srcDir, '/');
    $dstDir = rtrim($dstDir, '/');

    $command = "find $srcDir -type f";
    $templateFiles = run($command)->toArray();

    $result = [];
    foreach ($templateFiles as $src) {
        $name = str_replace($srcDir, '', $src);
        $dst = sprintf('%s%s', $dstDir, $name);
        $res = generateFile($src, $dst, null);
        if ($res) {
            $result[] = $dst;
        }
    }

    return $result;
}

function dirExists($dir)
{
    return run("if [ -d \"$dir\" ]; then echo true; fi")->toBool();
}

function fileExists($filename)
{
    return run("if [ -f \"$filename\" ]; then echo true; fi")->toBool();
}
