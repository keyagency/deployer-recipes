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
    private string $originalCwd;
    private string $workDir;

    protected function setUp(): void
    {
        $this->deployer = new Deployer(new Application());
        require_once __DIR__ . '/../recipe/key.php';

        /**
         * key_env() falls back to the .env in getcwd(), so run each test in
         * its own temporary directory to control whether a .env exists.
         */
        $this->originalCwd = getcwd();
        $this->workDir = sys_get_temp_dir() . '/deployer-key-test-' . uniqid();
        mkdir($this->workDir, 0777, true);
        chdir($this->workDir);
    }

    protected function tearDown(): void
    {
        putenv('KEY_SLACK_WEBHOOK');
        chdir($this->originalCwd);
        exec('rm -rf ' . escapeshellarg($this->workDir));
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
        // The webhook default is a lazy closure; without env or .env it resolves to ''.
        $this->assertInstanceOf(\Closure::class, $this->getRawConfig('key_slack_webhook'));
        $this->assertSame('', \Deployer\get('key_slack_webhook'));
        $this->assertSame('{{application}}', $this->getRawConfig('key_slack_title'));
        $this->assertSame('Deploy of `{{target}}` to *{{alias}}* on `{{hostname}}`', $this->getRawConfig('key_slack_text'));
        $this->assertSame('', $this->getRawConfig('key_healthcheck_url'));
        $this->assertSame(200, $this->getRawConfig('key_healthcheck_expected_status'));
        $this->assertSame(3, $this->getRawConfig('key_healthcheck_retries'));
        $this->assertSame(5, $this->getRawConfig('key_healthcheck_pause'));
    }

    public function testWebhookIsReadFromProjectDotEnv(): void
    {
        file_put_contents($this->workDir . '/.env', "APP_NAME=test\nKEY_SLACK_WEBHOOK=https://hooks.slack.com/services/abc\n");

        $this->assertSame('https://hooks.slack.com/services/abc', \Deployer\get('key_slack_webhook'));
    }

    public function testWebhookDotEnvQuotesAreStripped(): void
    {
        file_put_contents($this->workDir . '/.env', "KEY_SLACK_WEBHOOK=\"https://hooks.slack.com/services/abc\"\n");

        $this->assertSame('https://hooks.slack.com/services/abc', \Deployer\get('key_slack_webhook'));
    }

    public function testWebhookDotEnvLastAssignmentWins(): void
    {
        file_put_contents($this->workDir . '/.env', "KEY_SLACK_WEBHOOK=https://hooks.slack.com/first\nKEY_SLACK_WEBHOOK=https://hooks.slack.com/last\n");

        $this->assertSame('https://hooks.slack.com/last', \Deployer\get('key_slack_webhook'));
    }

    public function testWebhookDotEnvInlineCommentIsStripped(): void
    {
        file_put_contents($this->workDir . '/.env', "KEY_SLACK_WEBHOOK=https://hooks.slack.com/services/abc # production webhook\n");

        $this->assertSame('https://hooks.slack.com/services/abc', \Deployer\get('key_slack_webhook'));
    }

    public function testRealEnvironmentVariableWinsOverDotEnv(): void
    {
        file_put_contents($this->workDir . '/.env', "KEY_SLACK_WEBHOOK=https://hooks.slack.com/from-file\n");
        putenv('KEY_SLACK_WEBHOOK=https://hooks.slack.com/from-env');

        $this->assertSame('https://hooks.slack.com/from-env', \Deployer\get('key_slack_webhook'));
    }

    public function testExplicitSetWinsOverDotEnv(): void
    {
        file_put_contents($this->workDir . '/.env', "KEY_SLACK_WEBHOOK=https://hooks.slack.com/from-file\n");
        \Deployer\set('key_slack_webhook', 'https://hooks.slack.com/from-set');

        $this->assertSame('https://hooks.slack.com/from-set', \Deployer\get('key_slack_webhook'));
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

    /**
     * A connection-refused transport error must not escape key_slack_notify()
     * and abort the deploy (fire-and-forget behaviour).
     */
    public function testSlackNotifyDoesNotThrowWhenWebhookUnreachable(): void
    {
        /**
         * Set required placeholders so payload building does not throw before
         * the HTTP request is even attempted.
         */
        \Deployer\set('application', 'test-app');
        \Deployer\set('target', 'production');
        \Deployer\set('alias', 'production');
        \Deployer\set('hostname', 'localhost');

        // Connection refused must not abort a deploy (fire-and-forget).
        \Deployer\set('key_slack_webhook', 'http://127.0.0.1:9/');
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
     * The healthcheck must try key_healthcheck_retries times and then fail
     * with a message naming the attempt count. Port 9 (discard) refuses the
     * connection, so every attempt yields status 0 instead of the expected 200.
     */
    public function testHealthcheckRetriesBeforeFailing(): void
    {
        /**
         * fetch() and warning() need an output and a host context; the
         * container defaults throw.
         */
        $this->deployer['output'] = new \Symfony\Component\Console\Output\NullOutput();
        \Deployer\Task\Context::push(new \Deployer\Task\Context(new \Deployer\Host\Host('test')));

        \Deployer\set('key_healthcheck_url', 'http://127.0.0.1:9/health');
        \Deployer\set('key_healthcheck_retries', 2);
        \Deployer\set('key_healthcheck_pause', 0);

        $task = $this->deployer->tasks->get('key:healthcheck');
        $ref = new \ReflectionProperty($task, 'callback');
        $callback = $ref->getValue($task);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('after 2 attempt(s)');
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
