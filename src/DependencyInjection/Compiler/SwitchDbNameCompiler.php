<?php
namespace TheRat\SymDep\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use TheRat\SymDep\Helper\Database;

class SwitchDbNameCompiler implements CompilerPassInterface
{
    /**
     * You can modify the container here before it is dumped to PHP code.
     *
     * @param ContainerBuilder $container
     */
    public function process(ContainerBuilder $container)
    {
        if (!$container->getParameter('symdep.switch_db')
            || false === strpos($container->getParameter('database_driver'), 'mysql')
        ) {
            return;
        }

        $helper = $container->get('therat_symdep.helper.database');

        $originalDbName = $container->getParameter('database_name');
        $branchDbName = $helper->generateDatabaseName();

        $container->setParameter('database_name_original', $originalDbName);
        if ($originalDbName != $branchDbName) {
            if (!$helper->databaseExists($branchDbName)) {
                $helper->generateDatabase($branchDbName);
            }
            $container->setParameter('database_name', $branchDbName);

            $definition = $container->getDefinition('doctrine.dbal.default_connection');
            $connectionParams = $definition->getArgument(0);
            $connectionParams['dbname'] = $branchDbName;
            $definition->replaceArgument(0, $connectionParams);
        }
    }
}
