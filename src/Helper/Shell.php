<?php
namespace TheRat\SymDep\Helper;

class Shell
{
    /**
     * Create dir
     * @param $dir
     * @return bool
     */
    public static function mkdir($dir)
    {
        $command = "if [ ! -d $(echo $dir) ]; then mkdir -p $dir; echo 'true'; else echo 'false'; fi";
        return ('true' == ShellExec::run($command, true));
    }

    /**
     * @param $dir
     * @return bool
     */
    public static function dirExists($dir)
    {
        $result = ('true' == ShellExec::run("if [ -d \"$dir\" ]; then echo 'true'; fi"));
        return $result;
    }

    /**
     * @param $filename
     * @return bool
     */
    public static function fileExists($filename)
    {
        $result = ('true' == ShellExec::run("if [ -f \"$filename\" ]; then echo 'true'; fi"));
        return $result;
    }

    /**
     * @param $filename
     * @param $content
     * @return string
     */
    public static function filePutContent($filename, $content)
    {
        self::mkdir(dirname($filename));

        $command = <<<DOCHERE
cat > "$filename" <<'_EOF'
$content
_EOF
DOCHERE;
        return ShellExec::run($command, true);
    }

    /**
     * @param $filename
     * @return string
     */
    public static function fileGetContent($filename)
    {
        $command = "cat \"$filename\"";
        return ShellExec::run($command, true);
    }

    /**
     * @param string $dst
     * @param bool $mode
     * @param string $src
     * @return string
     */
    public static function chmod($dst, $mode = null, $src = null)
    {
        $command = "chmod $mode $dst";
        if (!$mode) {
            $command = "chmod --reference $src $dst";
        }
        return ShellExec::run($command, true);
    }

    /**
     * @param $command
     * @return bool
     */
    public static function commandExists($command)
    {
        $result = ('true' == trim(ShellExec::run("if hash $command 2>/dev/null; then echo 'true'; fi", true)));
        return $result;
    }

    public static function touch($filename)
    {
        $command = "if [ ! -f \"$filename\" ]; then touch \"$filename\"; fi";
        return ShellExec::run($command, true);
    }
}
