<?php
namespace TheRat\SymDep\Helper;

use Doctrine\DBAL\DriverManager;
use Symfony\Component\DependencyInjection\ContainerAwareInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Process\Process;

class Database implements ContainerAwareInterface
{
    /**
     * @var ContainerInterface
     */
    protected $container;

    protected $tmpConnection;

    public static function copyDbData(array $params, $originalDbName, $name)
    {
        $command = 'mysqldump -h' . $params['host'] . ' -u' . $params['user'] . ' -p' . $params['password'] . ' ' . $originalDbName . ' | ' .
            'mysql -h' . $params['host'] . ' -u' . $params['user'] . ' -p' . $params['password'] . ' ' . $name;
        passthru($command);

    }

    /**
     * Sets the Container.
     *
     * @param ContainerInterface|null $container A ContainerInterface instance or null
     *
     * @api
     */
    public function setContainer(ContainerInterface $container = null)
    {
        $this->container = $container;
    }

    public function generateDatabaseName()
    {
        $rootPath = $this->container->getParameter('kernel.root_dir');
        $cmd = 'cd ' . $rootPath . ' && git symbolic-ref HEAD';
        $branchRef = exec($cmd);

        $result = $this->container->getParameter('database_name');
        if (strpos($branchRef, 'refs/heads/') === 0) {
            $branchName = $this->prepareBranchName(substr($branchRef, 11));
            if ('master' != $branchName) {
                $result = $result . '_branch_' . $branchName;
            }
        }
        return $result;
    }

    public function databaseExists($name)
    {
        if (in_array($name, $this->getTmpConnection()->getSchemaManager()->listDatabases())) {
            return true;
        }
        return false;
    }

    public function generateDatabase($dstDbName)
    {
        $connection = $this->getTmpConnection();
        $connection->getSchemaManager()->createDatabase($dstDbName);

        $container = $this->container;
        if ($container->getParameter('symdep.copy_db_data')) {
            $host = $container->getParameter('database_host');
            $user = $container->getParameter('database_user');
            $password = $container->getParameter('database_password');
            $srcDbName = $container->getParameter('database_name');

            $cmd = "mysqldump -h{$host} -u{$user} -p{$password} {$srcDbName}" .
                " | mysql -h{$host} -u{$user} -p{$password} {$dstDbName}";

            $process = new Process(
                $cmd,
                null,
                null,
                null,
                60 * 15
            );
            $process->mustRun();
        }
    }

    protected function prepareBranchName($branchName)
    {
        $result = str_replace('-', '_', strtolower($branchName));
        $pos = strrpos($branchName, '/');
        if (false !== $pos) {
            $result = substr($result, $pos + 1);
        }
        return $result;
    }

    protected function getTmpConnection()
    {
        if (!$this->tmpConnection) {
            $container = $this->container;
            $params = [
                'driver' => $container->getParameter('database_driver'),
                'host' => $container->getParameter('database_host'),
                'port' => $container->getParameter('database_port'),
                'dbname' => $container->getParameter('database_name_original'),
                'user' => $container->getParameter('database_user'),
                'password' => $container->getParameter('database_password'),
            ];
            $this->tmpConnection = DriverManager::getConnection($params);
        }
        return $this->tmpConnection;
    }
}
