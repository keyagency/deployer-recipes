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
    public function testWrapperRegistersBaseAndKeyTasks(string $file, array $extraTasks): void
    {
        $deployer = new Deployer(new Application());
        require __DIR__ . '/../recipe/key/' . $file;

        $this->assertTrue($deployer->tasks->has('deploy'), "base 'deploy' missing in $file");
        $this->assertTrue($deployer->tasks->has('key:notify:start'), "key tasks missing in $file");
        foreach ($extraTasks as $task) {
            $this->assertTrue($deployer->tasks->has($task), "task '$task' missing in $file");
        }
    }

    public static function wrappers(): array
    {
        return [
            'laravel' => ['laravel.php', []],
            'statamic' => ['statamic.php', [
                'key:build:resources',
                'key:sync:content',
                'key:sync:assets',
                'key:sync:forms',
                'key:sync:addons',
            ]],
            'october' => ['october.php', [
                'key:sync:theme',
                'key:sync:storage',
            ]],
            'bedrock' => ['bedrock.php', [
                'key:build:resources',
                'key:install:languages',
            ]],
        ];
    }
}
