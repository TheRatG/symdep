<?php
namespace TheRat\SymDep;

use TheRat\SymDep\DependencyInjection\Compiler\SwitchDbNameCompiler;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class SymDepBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new SwitchDbNameCompiler());
    }
}
