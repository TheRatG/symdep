<?php
namespace SymDep\Helper;

/**
 * Class GenerateFile
 *
 * Create file from templates
 *
 * @package symdep\Helper
 */
class GenerateFile
{
    static public $openBracket = '{{';
    static public $closeBracket = '}}';

    function globRecursive($pattern, $flags = 0)
    {
        $files = glob($pattern, $flags);

        foreach (glob(dirname($pattern) . '/*', GLOB_ONLYDIR | GLOB_NOSORT) as $dir) {
            $tmp = globRecursive($dir . '/' . basename($pattern), $flags);
            $files = array_merge($files, $tmp);
        }

        foreach ($files as $key => $value) {
            if (!is_file($value)) {
                unset($files[$key]);
            }
        }

        return $files;
    }

    public function generateFiles($srcDir, $dstDir, array $placeholders = [])
    {
        $srcDir = rtrim($srcDir, '/');
        $dstDir = rtrim($dstDir, '/');
        $templateFiles = $this->globRecursive($srcDir);
        $result = [];
        foreach ($templateFiles as $src) {
            if (!is_file($src)) {
                continue;
            }

            $name = str_replace($srcDir, '', $src);
            $dst = sprintf('%s%s', $dstDir, $name);
            $res = $this->generateFile($src, $dst, $placeholders);
            if ($res) {
                $result[] = $dst;
            }
        }
        return $result;
    }

    public function generateFile($src, $dst, array $placeholders = [], $mode = null)
    {
        $content = file_get_contents($src);

        $keys = array_keys($placeholders);
        $keys = array_map($keys, [$this, 'cover']);
        $content = str_replace($keys, array_values($placeholders), $content);
        if (!$content) {
            throw new \InvalidArgumentException('Src file is empty');
        }

        $result = file_put_contents($dst, $content);

        if (is_null($mode)) {
            @chmod($dst, fileperms($src));
        }

        return $result;
    }

    protected function cover($item)
    {
        return self::$openBracket . $item . self::$closeBracket;
    }
}
