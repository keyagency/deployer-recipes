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
final class SyncHelpersTest extends TestCase
{
    private Deployer $deployer;
    private string $originalCwd;
    private string $workDir;

    protected function setUp(): void
    {
        $this->deployer = new Deployer(new Application());
        // info()/runLocally() need an output and a host context; the defaults throw.
        $this->deployer['output'] = new NullOutput();
        Context::push(new Context(new Host('test')));
        require_once __DIR__ . '/../recipe/helpers/sync.php';

        /**
         * key_sync_backup_destination() resolves local paths against getcwd(),
         * so run each test inside its own temporary directory.
         */
        $this->originalCwd = getcwd();
        $this->workDir = sys_get_temp_dir() . '/deployer-sync-test-' . uniqid();
        mkdir($this->workDir, 0777, true);
        chdir($this->workDir);
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        \Deployer\runLocally('rm -rf ' . escapeshellarg($this->workDir));
    }

    public function testBackupCopiesLocalDestinationBeforeSync(): void
    {
        mkdir($this->workDir . '/content');
        file_put_contents($this->workDir . '/content/page.md', 'original');

        \Deployer\key_sync_backup_destination('content/', true);

        $this->assertFileExists($this->workDir . '/content-backup/page.md');
        $this->assertSame('original', file_get_contents($this->workDir . '/content-backup/page.md'));
    }

    /**
     * The backup mirrors the destination: files from an older backup that no
     * longer exist in the destination must be deleted, so the backup always
     * reflects the state right before the last sync.
     */
    public function testBackupMirrorsCurrentDestinationState(): void
    {
        mkdir($this->workDir . '/content');
        file_put_contents($this->workDir . '/content/page.md', 'v2');
        mkdir($this->workDir . '/content-backup');
        file_put_contents($this->workDir . '/content-backup/stale.md', 'old');

        \Deployer\key_sync_backup_destination('content/', true);

        $this->assertFileDoesNotExist($this->workDir . '/content-backup/stale.md');
        $this->assertSame('v2', file_get_contents($this->workDir . '/content-backup/page.md'));
    }

    public function testBackupIsNoOpWhenLocalDestinationMissing(): void
    {
        \Deployer\key_sync_backup_destination('content/', true);

        $this->assertDirectoryDoesNotExist($this->workDir . '/content-backup');
    }

    /**
     * Remote paths are built before Deployer expands {{current_path}}, so the
     * placeholder must stay outside the shell quoting. Quoting it along with
     * the rest would hide a leading ~ from the remote shell, which then reads
     * it as a literal directory name and reports every path as missing.
     */
    public function testRemotePathLeavesPlaceholderUnquoted(): void
    {
        $this->assertSame('{{current_path}}/content/', \Deployer\key_remote_path('content/'));
    }

    public function testRemotePathQuotesUnsafeCharactersInTheSubPath(): void
    {
        $path = \Deployer\key_remote_path('my content/');

        $this->assertStringStartsWith('{{current_path}}/', $path);
        $this->assertStringNotContainsString("{{current_path}}/'", $path);
        $this->assertMatchesRegularExpression('/^\{\{current_path\}\}\/\$\'my content\/\'$/', $path);
    }
}
