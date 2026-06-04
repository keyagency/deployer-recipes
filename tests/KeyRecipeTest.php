<?php
namespace Keyagency\DeployerRecipes\Tests;

use Deployer\Configuration;
use Deployer\Deployer;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;

/**
 * Each test method runs in its own PHP process so the recipe file (and any
 * functions it defines) loads exactly once per method — no "cannot redeclare"
 * fatals and no cross-method singleton contamination.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class KeyRecipeTest extends TestCase
{
    private Deployer $deployer;

    protected function setUp(): void
    {
        $this->deployer = new Deployer(new Application());
        require_once __DIR__ . '/../recipe/key.php';
    }

    /**
     * Returns the raw (unresolved) value stored in Configuration without
     * triggering placeholder expansion.
     */
    private function getRawConfig(string $key): mixed
    {
        $ref = new \ReflectionClass(Configuration::class);
        $method = $ref->getMethod('fetch');
        return $method->invoke($this->deployer->config, $key);
    }

    public function testConfigDefaults(): void
    {
        $this->assertSame('', $this->getRawConfig('slack_webhook'));
        $this->assertSame('{{application}}', $this->getRawConfig('slack_title'));
        $this->assertSame('Deploy of `{{target}}` on *{{hostname}}*', $this->getRawConfig('slack_text'));
        $this->assertSame('', $this->getRawConfig('healthcheck_url'));
        $this->assertSame(200, $this->getRawConfig('healthcheck_expected_status'));
    }

    public function testNotifyTasksRegistered(): void
    {
        $this->assertTrue($this->deployer->tasks->has('key:notify:start'));
        $this->assertTrue($this->deployer->tasks->has('key:notify:success'));
        $this->assertTrue($this->deployer->tasks->has('key:notify:failure'));
    }

    /**
     * Httpie::send() throws for an empty URL, so a missing no-op guard would
     * make this call throw. No throw means the guard works.
     */
    public function testSlackNotifyIsNoOpWhenWebhookIsEmpty(): void
    {
        $this->expectNotToPerformAssertions();
        \Deployer\key_slack_notify('#cccccc', 'started');
    }

    public function testHealthcheckTaskRegistered(): void
    {
        $this->assertTrue($this->deployer->tasks->has('key:healthcheck'));
    }

    public function testHealthcheckIsNoOpWhenUrlIsEmpty(): void
    {
        $this->expectNotToPerformAssertions();
        $task = $this->deployer->tasks->get('key:healthcheck');
        // Task::$callback is private with no public getter; use reflection to invoke it.
        $ref = new \ReflectionProperty($task, 'callback');
        $callback = $ref->getValue($task);
        $callback();
    }

    /**
     * Verifies that the hooks wired in recipe/key.php are registered on the
     * correct Deployer tasks.
     */
    public function testDeployHooksWired(): void
    {
        $deployTask = $this->deployer->tasks->get('deploy');
        $successTask = $this->deployer->tasks->get('deploy:success');
        $failedTask = $this->deployer->tasks->get('deploy:failed');

        $this->assertContains('key:notify:start', $deployTask->getBefore(), 'deploy task should run key:notify:start before it');
        $this->assertContains('key:notify:failure', $failedTask->getAfter(), 'deploy:failed task should run key:notify:failure after it');

        $successAfter = $successTask->getAfter();
        $healthcheckIdx = array_search('key:healthcheck', $successAfter, true);
        $notifySuccessIdx = array_search('key:notify:success', $successAfter, true);
        $this->assertNotFalse($healthcheckIdx, 'deploy:success task should run key:healthcheck after it');
        $this->assertNotFalse($notifySuccessIdx, 'deploy:success task should run key:notify:success after it');
        $this->assertLessThan($notifySuccessIdx, $healthcheckIdx, 'key:healthcheck must run before key:notify:success so success is only reported when healthy');
    }
}
