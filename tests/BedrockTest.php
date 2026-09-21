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
        require_once __DIR__ . '/../recipe/key/bedrock/wordfence.php';
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

    public function testWordfenceTaskRegistered(): void
    {
        $this->assertTrue($this->deployer->tasks->has('key:wordfence:fix-waf'));
    }

    /**
     * Without wordfence_waf_file the task must return before test()/run(),
     * which need a real connection. No throw means the guard works, so the
     * task is harmless on hosts without Wordfence.
     *
     * The null check is the precondition, not a formality: once anything sets
     * a default for wordfence_waf_file, the callback reaches test() and blocks
     * on an SSH connection for about a minute before failing. Asserting it
     * here turns that CI hang into an immediate, self-explaining failure.
     */
    public function testWordfenceIsNoOpWhenWafFileIsNotConfigured(): void
    {
        $this->assertNull(\Deployer\get('wordfence_waf_file', null));

        $this->taskCallback('key:wordfence:fix-waf')();
    }

    // Task::$callback is private with no public getter; use reflection to read it.
    private function taskCallback(string $name): \Closure
    {
        $task = $this->deployer->tasks->get($name);
        return (new \ReflectionProperty($task, 'callback'))->getValue($task);
    }
}
