<?php
/**
 * Created by PhpStorm.
 * User: Nils
 * Date: 21.11.2018
 * Time: 20:02
 */

namespace SerethiX\ComposerCommandLogPlugin;


use Composer\Composer;

class LoggingCommandProvider implements \Composer\Plugin\Capability\CommandProvider
{
    /**
     * @var Composer
     */
    protected $composer;

    public function __construct(array $options)
    {
        $this->composer = $options['composer'];
    }

    public function getCommands()
    {
        $replayCommand = new LoggingReplayCommand($this->composer);

        return [$replayCommand];
    }

}
