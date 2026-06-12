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
final class StatamicSyncTest extends TestCase
{
    private Deployer $deployer;

    protected function setUp(): void
    {
        $this->deployer = new Deployer(new Application());
        // info()/writeln() need an output and a host context; the defaults throw.
        $this->deployer['output'] = new NullOutput();
        Context::push(new Context(new Host('test')));
        require_once __DIR__ . '/../recipe/key/statamic/sync.php';
    }

    public function testSyncHasDetectsConfiguredTypes(): void
    {
        $this->assertTrue(\Deployer\key_sync_has('content'));
        $this->assertTrue(\Deployer\key_sync_has('assets'));
        $this->assertTrue(\Deployer\key_sync_has('forms'));
        $this->assertTrue(\Deployer\key_sync_has('addons'));
        $this->assertFalse(\Deployer\key_sync_has('nonexistent'));
    }

    public function testAddAppendsPathsToDefaults(): void
    {
        \Deployer\add('key_sync_content', ['resources/navigation/']);

        $paths = \Deployer\get('key_sync_content');
        $this->assertContains('content/', $paths);
        $this->assertContains('resources/sites.yaml', $paths);
        $this->assertContains('resources/navigation/', $paths);
    }

    /**
     * Syncing a type without configured paths must skip without touching
     * rsync or the remote (no Context/host is set up here, so any attempt
     * to actually sync would throw).
     */
    public function testSyncIsNoOpForEmptyType(): void
    {
        $this->expectNotToPerformAssertions();
        \Deployer\set('key_sync_addons', []);
        \Deployer\key_sync('addons', true, true);
    }

    public function testSyncReportsUnknownTypeWithoutThrowing(): void
    {
        $this->expectNotToPerformAssertions();
        \Deployer\key_sync('bogus', true, true);
    }
}
