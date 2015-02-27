<?php
namespace TheRat\SymDep\Helper;

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
        $result = $this->run($command);
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

    public function generateFile($src, $dst, array $placeholders = [], $mode = null)
    {
        $content = $this->fileGetContent($src);
        $keys = array_keys($placeholders);
        $keys = array_map([$this, 'cover'], $keys);
        $content = str_replace($keys, array_values($placeholders), $content);
        if (!$content) {
            throw new \InvalidArgumentException('Src file is empty');
        }

        $result = $this->filePutContent($dst, $content);

        $this->chmod($dst, $mode, $src);

        return $result;
    }

    protected function cover($item)
    {
        return self::$openBracket . $item . self::$closeBracket;
    }

    protected function filePutContent($filename, $content)
    {
        $command = <<<DOCHERE
cat > "$filename" <<'_EOF'
$content
_EOF
DOCHERE;
        $this->run($command);
    }

    protected function fileGetContent($filename)
    {
        $command = "cat $filename";
        $result = $this->run($command);
        return $result;
    }

    protected function run($command, $raw = true)
    {
        return RunHelper::exec($command, $raw);
    }

    protected function chmod($dst, $mode, $src)
    {
        $command = "chmod $mode $dst";
        if (!$mode) {
            $command = "chmod --reference $src $dst";
        }
        $this->run($command);
    }
}
