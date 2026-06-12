<?php

namespace Keyagency\DeployerRecipes\Tests;

use Deployer\Deployer;
use Deployer\Host\Host;
use Deployer\Task\Context;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Output\NullOutput;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class OctoberSyncTest extends TestCase
{
    private Deployer $deployer;

    protected function setUp(): void
    {
        $this->deployer = new Deployer(new Application());
        // info()/writeln() need an output and a host context; the defaults throw.
        $this->deployer['output'] = new NullOutput();
        Context::push(new Context(new Host('test')));
        require_once __DIR__ . '/../recipe/key/october/sync.php';
    }

    public function testSyncHasDetectsConfiguredTypes(): void
    {
        $this->assertTrue(\Deployer\key_sync_has('theme'));
        $this->assertTrue(\Deployer\key_sync_has('storage'));
        $this->assertFalse(\Deployer\key_sync_has('nonexistent'));
    }

    public function testThemePathsFollowConfiguredThemes(): void
    {
        // Set after the require: the lazy closure must pick this up on first get().
        \Deployer\set('key_october_themes', ['alpha', 'beta']);

        $this->assertSame([
            'themes/alpha/content/',
            'themes/alpha/meta/',
            'themes/beta/content/',
            'themes/beta/meta/',
        ], \Deployer\get('key_sync_theme'));
    }

    public function testStoragePathsTargetUploadsAndMedia(): void
    {
        $this->assertSame(['storage/app/uploads/', 'storage/app/media/'], \Deployer\get('key_sync_storage'));
    }

    public function testDefaultExcludesContainBlocksYaml(): void
    {
        $this->assertContains('blocks.yaml', \Deployer\get('key_sync_excludes'));
    }
}
