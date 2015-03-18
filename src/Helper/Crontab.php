<?php
namespace TheRat\SymDep\Helper;

class Crontab
{
    public function update($crontabFile)
    {
        $crontabFileBak = $crontabFile . '_bak';
        $DIFF = '$DIFF';
        $command = <<<BASH
# backup your current crontab file
crontab -l > $crontabFileBak
DIFF=$(diff "$crontabFileBak" "$crontabFile")
if [ "$DIFF" != "" ]; then
    # # this will request crontab to run using this new command file
    crontab $crontabFile
    # # output crontab to validate your updates
    crontab -l
else
    echo "Crontab has no diff"
    rm "$crontabFileBak";
fi
BASH;
        return ShellExec::run($command, true);
    }
}
