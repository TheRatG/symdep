<?php
namespace TheRat\SymDep\Helper;

/**
 * Class GenerateFile
 *
 * Create file from templates
 *
 */
class GenerateFile
{
    static public $openBracket = '{{';
    static public $closeBracket = '}}';
    /**
     * @var bool
     */
    protected $remote;

    public function __construct($remote)
    {
        $this->remote = $remote;
    }

    /**
     * @return boolean
     */
    public function isRemote()
    {
        return $this->remote;
    }

    function globRecursive($pattern, $flags = 0)
    {
        $files = glob($pattern, $flags);

        foreach (glob(dirname($pattern) . '/*', GLOB_ONLYDIR | GLOB_NOSORT) as $dir) {
            $tmp = $this->globRecursive($dir . '/' . basename($pattern), $flags);
            $files = array_merge($files, $tmp);
        }

        foreach ($files as $key => $value) {
            if (!is_file($value)) {
                unset($files[$key]);
            }
        }

        return $files;
    }

    public function findFiles($srcDir)
    {
        $command = "find $srcDir -type f";
        $result = ShellExec::run($command, true);
        $result = explode("\n", trim($result));
        return $result;
    }

    public function generateFiles($srcDir, $dstDir, array $placeholders = [])
    {
        $srcDir = rtrim($srcDir, '/');
        $dstDir = rtrim($dstDir, '/');
        $templateFiles = $this->findFiles($srcDir);
        $result = [];
        foreach ($templateFiles as $src) {
            $name = str_replace($srcDir, '', $src);
            $dst = sprintf('%s%s', $dstDir, $name);
            $res = $this->generateFile($src, $dst, $placeholders);
            if ($res) {
                $result[] = $dst;
            }
        }
        return $result;
    }

    /**
     * @param $src
     * @param $dst
     * @param array $placeholders
     * @param null $mode
     * @return string
     */
    public function generateFile($src, $dst, array $placeholders = [], $mode = null)
    {
        $content = Shell::fileGetContent($src);
        $keys = array_keys($placeholders);
        $keys = array_map([$this, 'cover'], $keys);
        $content = str_replace($keys, array_values($placeholders), $content);
        if (!$content) {
            throw new \InvalidArgumentException('Src file is empty');
        }

        $result = Shell::filePutContent($dst, $content);
        Shell::chmod($dst, $mode, $src);
        return $result;
    }

    protected function cover($item)
    {
        return self::$openBracket . $item . self::$closeBracket;
    }
}
