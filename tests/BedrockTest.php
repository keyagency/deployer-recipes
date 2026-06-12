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
final class BedrockTest extends TestCase
{
    private Deployer $deployer;

    protected function setUp(): void
    {
        $this->deployer = new Deployer(new Application());
        // info()/writeln() need an output and a host context; the defaults throw.
        $this->deployer['output'] = new NullOutput();
        Context::push(new Context(new Host('test')));
        require_once __DIR__ . '/../recipe/key/bedrock/build.php';
        require_once __DIR__ . '/../recipe/key/bedrock/languages.php';
    }

    public function testThemePathFollowsConfiguredTheme(): void
    {
        // Set after the require: the lazy closure must pick this up on first get().
        \Deployer\set('key_bedrock_theme', 'my-theme');

        $this->assertSame('web/app/themes/my-theme', \Deployer\get('key_bedrock_theme_path'));
    }

    public function testBuildUploadsDefaultToJsAssetsAndStylesheet(): void
    {
        $this->assertSame(['assets/js/', 'style.css'], \Deployer\get('key_build_uploads'));
    }

    public function testAddAppendsUploadsToDefaults(): void
    {
        \Deployer\add('key_build_uploads', ['assets/css/']);

        $uploads = \Deployer\get('key_build_uploads');
        $this->assertContains('assets/js/', $uploads);
        $this->assertContains('assets/css/', $uploads);
    }

    public function testLanguagesDefaultToDutch(): void
    {
        $this->assertSame(['nl_NL'], \Deployer\get('key_languages'));
    }
}
