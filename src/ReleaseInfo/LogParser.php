<?php
namespace TheRat\SymDep\ReleaseInfo;

class LogParser
{
    protected $pattern = '/[a-z]+-\d+/i';

    public function execute(array $log)
    {
        $result = [];
        foreach ($log as $subject) {
            if (preg_match_all($this->pattern, $subject, $matches)) {
                $result = array_merge($result, array_shift($matches));
            }
        }

        $result = array_values(array_unique(array_filter($result)));
        return $result;
    }
}
