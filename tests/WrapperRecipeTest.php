<?php

namespace Keyagency\DeployerRecipes\Tests;

use Deployer\Deployer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class WrapperRecipeTest extends TestCase
{
    #[DataProvider('wrappers')]
    public function testWrapperRegistersBaseAndKeyTasks(string $file): void
    {
        $deployer = new Deployer(new Application());
        require __DIR__ . '/../recipe/key/' . $file;

        $this->assertTrue($deployer->tasks->has('deploy'), "base 'deploy' missing in $file");
        $this->assertTrue($deployer->tasks->has('key:notify:start'), "key tasks missing in $file");
    }

    public static function wrappers(): array
    {
        return [
            'laravel' => ['laravel.php'],
            'statamic' => ['statamic.php'],
            'october' => ['october.php'],
        ];
    }
}
