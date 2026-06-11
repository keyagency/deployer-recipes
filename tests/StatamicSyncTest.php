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

    public function testSyncMapHasDetectsConfiguredTypes(): void
    {
        $this->assertTrue(\Deployer\key_sync_map_has('content'));
        $this->assertTrue(\Deployer\key_sync_map_has('assets'));
        $this->assertTrue(\Deployer\key_sync_map_has('forms'));
        // addons ships empty by default and must be reported as unconfigured.
        $this->assertFalse(\Deployer\key_sync_map_has('addons'));
        $this->assertFalse(\Deployer\key_sync_map_has('nonexistent'));
    }

    /**
     * Syncing a type without configured paths must skip without touching
     * rsync or the remote (no Context/host is set up here, so any attempt
     * to actually sync would throw).
     */
    public function testSyncIsNoOpForEmptyType(): void
    {
        $this->expectNotToPerformAssertions();
        \Deployer\key_sync('addons', true, true);
    }

    public function testSyncReportsUnknownTypeWithoutThrowing(): void
    {
        $this->expectNotToPerformAssertions();
        \Deployer\key_sync('bogus', true, true);
    }
}
