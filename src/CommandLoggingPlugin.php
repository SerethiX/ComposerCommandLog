<?php
/**
 * Created by PhpStorm.
 * User: Nils
 * Date: 21.11.2018
 * Time: 19:58
 */

namespace SerethiX\ComposerCommandLogPlugin;


use Composer\Composer;
use Composer\Config;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\Capability\CommandProvider;
use Composer\Plugin\Capable;
use Composer\Plugin\CommandEvent;
use Composer\Plugin\PluginInterface;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Process\Process;

class CommandLoggingPlugin implements PluginInterface, Capable, EventSubscriberInterface
{
    use LockableTrait;

    public const CONFIG_COMMAND_MODE = 'command-mode';
    public const CONFIG_LOG_FILE     = 'command-logfile';
    public const CONFIG_LOG_COMMANDS = 'command-to-log';

    public const CONFIG_DEFAULTS = [
        self::CONFIG_LOG_FILE     => 'composer-command.log',
        self::CONFIG_LOG_COMMANDS => [
            "require",
            "remove",
            "update",
            "upgrade",
            "run-script",
            "exec",
            "dumpautoload",
            "dump-autoload",
            "config",
        ],
    ];

    /**
     * @var Composer
     */
    protected $composer;

    /**
     * @var IOInterface
     */
    protected $io;

    /**
     * @var Logger
     */
    protected $logger;

    /**
     * @param Composer    $composer
     * @param IOInterface $io
     */
    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io       = $io;

        $ds  = DIRECTORY_SEPARATOR;
        $cwd = getcwd() . $ds;

        $config = $composer->getConfig();

        $logfile = self::getConfiguration($config, self::CONFIG_LOG_FILE);

        if (empty($logfile) || !is_string($logfile)) {
            $configName = self::CONFIG_LOG_FILE;

            throw new \Exception("You can either omit the config value for '{$configName}' or provide a filename relative to your project directory, but do not leave it empty.");
        }

        $io->write("Writing command logs to $cwd$logfile", true, IOInterface::VERBOSE);

        $this->logger = new Logger($logfile);
    }

    /**
     * @param Config $config
     * @param string $name
     *
     * @return mixed
     */
    public static function getConfiguration(Config $config, string $name)
    {
        $value = self::CONFIG_DEFAULTS[$name];

        if ($config->has($name)) {
            $value = $config->get($name);
        }

        return $value;
    }

    public function getCapabilities()
    {
        return [
            CommandProvider::class => LoggingCommandProvider::class,
        ];
    }

    public static function getSubscribedEvents()
    {
        return [
            'command' => ['onCommand', 100],
        ];
    }

    /**
     * Checks what kind of command is being executed at this point and logs them via the Logger class.
     *
     * @param CommandEvent $event
     */
    public function onCommand(CommandEvent $event)
    {
        $config = $this->composer->getConfig();

        if (!$this->lock(self::CONFIG_COMMAND_MODE, false)) {
            $this->io->write("Not logging because we are replaying currently", IOInterface::VERBOSE);

            return;
        }
        $this->release();

        $input = $event->getInput();

        $logCommands = self::getConfiguration($config, self::CONFIG_LOG_COMMANDS);

        if (!is_array($logCommands)) {
            $configName = self::CONFIG_LOG_COMMANDS;
            $type       = gettype($logCommands);

            throw new \Exception("{$configName} is required to be an array, got {$type}");
        }

        if (in_array($event->getCommandName(), $logCommands)) {
            $version = $this->logger->log($input, $this->getGitTag());

            $this->io->write("Logged call to {$event->getCommandName()} as version {$version}");
        } else {
            $this->io->write("Skipping log of {$event->getCommandName()}, it is not one of the whitelisted commands: "
                             . implode(', ', $logCommands),
                             true,
                             IOInterface::VERY_VERBOSE);
        }
    }

    /**
     * Returns the current branch name.
     *
     * @return null|string
     */
    protected function getGitTag(): ?string
    {
        $process = new Process('git rev-parse --abbrev-ref HEAD');

        $process->run();

        if (!$process->isSuccessful()) {
            $this->io->write("GIT FALSE", true, IOInterface::DEBUG);

            return null;
        }

        return trim($process->getOutput());
    }
}
