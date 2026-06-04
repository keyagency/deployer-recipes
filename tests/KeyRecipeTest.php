<?php
namespace Keyagency\DeployerRecipes\Tests;

use Deployer\Configuration;
use Deployer\Deployer;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;

// Each test method runs in its own PHP process so the recipe file (and any
// functions it defines) loads exactly once per method — no "cannot redeclare"
// fatals and no cross-method singleton contamination.
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
final class KeyRecipeTest extends TestCase
{
    private Deployer $deployer;

    protected function setUp(): void
    {
        // Fresh Deployer instance per test; require_once is correct here because
        // each isolated process loads this file exactly once.
        $this->deployer = new Deployer(new Application());
        require_once __DIR__ . '/../recipe/key.php';
    }

    /**
     * Returns the raw (unresolved) value stored in Configuration without
     * triggering placeholder expansion. Uses reflection to call the
     * protected fetch() method on the Configuration instance.
     */
    private function getRawConfig(string $key): mixed
    {
        $ref = new \ReflectionClass(Configuration::class);
        $method = $ref->getMethod('fetch');
        return $method->invoke($this->deployer->config, $key);
    }

    public function testConfigDefaults(): void
    {
        // No webhook/url baked in: safe defaults for a public package.
        // Use getRawConfig for all values so placeholder strings are never expanded.
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

    public function testSlackNotifyIsNoOpWhenWebhookIsEmpty(): void
    {
        // slack_webhook defaults to '' (public-package safety guarantee).
        // Httpie::send() throws RuntimeException('URL must not be empty ...') for an
        // empty URL, so if the guard were missing this call would throw. No throw = no-op.
        $this->expectNotToPerformAssertions();
        \Deployer\key_slack_notify('#cccccc', 'started');
    }

}
