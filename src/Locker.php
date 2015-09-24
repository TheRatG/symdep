<?php
namespace TheRat\SymDep;

/**
 * Class Locker
 * @package TheRat\SymDep
 */
class Locker
{
    const STATUS_PROCESS = 'process';
    const STATUS_COMPLETE = 'complete';

    const INFO_CREATED_AT_KEY = 'createdAt';
    const INFO_UPDATED_AT_KEY = 'updatedAt';
    const INFO_STATUS_KEY = 'status';

    /**
     * @param string $filename
     * @param int $keep Lock filename lifetime in minutes (default 15)
     */
    public function __construct($filename, $keep = null)
    {
        $this->filename = $filename;
        $this->keep = null !== $keep ? $keep : 15;
    }

    /**
     * Check lock file
     * @return bool
     */
    public function isLocked()
    {
        $result = false;
        $info = $this->info();
        if (!empty($info[self::INFO_CREATED_AT_KEY])) {
            $date = new \DateTime($info[self::INFO_CREATED_AT_KEY]);
            $interval = $date->diff((new \DateTime()));
            if ($interval->i < $this->keep
                && (!empty($info[self::INFO_STATUS_KEY]) && self::STATUS_PROCESS === $info[self::INFO_STATUS_KEY])
            ) {
                $result = true;
            }
        }
        return $result;
    }

    /**
     * Create lock file
     * @param array $additionalInfo
     */
    public function lock(array $additionalInfo = [])
    {
        $info = [
            self::INFO_CREATED_AT_KEY => date('c'),
            self::INFO_UPDATED_AT_KEY => date('c'),
            self::INFO_STATUS_KEY => self::STATUS_PROCESS,
        ];
        $info = array_merge($info, $additionalInfo);
        $this->write($info);
    }

    /**
     * Update lock file
     */
    public function unlock()
    {
        $info = $this->info();
        $info[self::INFO_UPDATED_AT_KEY] = date('c');
        $info[self::INFO_STATUS_KEY] = self::STATUS_COMPLETE;
        $this->write($info);
    }

    /**
     * @return array
     */
    public function info()
    {
        $result = [
            self::INFO_CREATED_AT_KEY => null,
            self::INFO_UPDATED_AT_KEY => null,
            self::INFO_STATUS_KEY => null,
        ];
        if (fileExists($this->filename)) {
            $content = trim((string)\TheRat\SymDep\runCommand("cat '$this->filename'"));
            $data = json_decode($content, true);
            if ($data) {
                $result = $data;
            }
        }
        return $result;
    }

    public function __toString()
    {
        $info = $this->info();
        $result = [];
        foreach ($info as $key => $item) {
            $result[] = $key . ': "' . $item . '""';
        }
        return implode("\n", $result);
    }

    protected function write(array $info)
    {
        $content = json_encode($info);
        $command = <<<DOCHERE
cat > "$this->filename" <<'_EOF'
$content
_EOF
DOCHERE;
        \TheRat\SymDep\runCommand($command);
    }
}
