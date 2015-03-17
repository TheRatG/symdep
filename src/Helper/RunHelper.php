<?php
namespace TheRat\SymDep\Helper;

class RunHelper
{
    /**
     * @var bool
     */
    static protected $remote = true;

    /**
     * @return boolean
     */
    public static function isRemote()
    {
        return self::$remote;
    }

    /**
     * @param boolean $remote
     */
    public static function setRemote($remote)
    {
        self::$remote = $remote;
    }

    public static function exec($command, $raw = true)
    {
        if (self::isRemote()) {
            $result = run($command, $raw);
        } else {
            $result = self::execLocally($command, $raw);
        }
        return $result;
    }

    public static function execLocally($command, $raw = true)
    {
        if (!$raw) {
            $workingPath = env()->getWorkingPath();
            $command = "cd {$workingPath} && $command";
        }

        if (output()->isDebug()) {
            writeln($command);
        }

        $output = runLocally($command);

        if (output()->isDebug()) {
            array_map(function ($output) {
                writeln($output);
            }, explode("\n", $output));
        }

        return $output;
    }
}
