<?php
namespace Keyagency\DeployerRecipes\Tests;

use Deployer\Deployer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;

final class KeyRecipeTest extends TestCase
{
    private Deployer $deployer;

    protected function setUp(): void
    {
        // Fresh Deployer instance per test; require recipe under test.
        $this->deployer = new Deployer(new Application());
        require __DIR__ . '/../recipe/key.php';
    }

    public function testConfigDefaults(): void
    {
        // No webhook/url baked in: safe defaults for a public package.
        $this->assertSame('', $this->deployer->config['slack_webhook']);
        $this->assertSame('', $this->deployer->config['healthcheck_url']);
        $this->assertSame(200, $this->deployer->config['healthcheck_expected_status']);
    }
}
