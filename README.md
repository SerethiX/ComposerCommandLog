# README

Are you also stressed by handling a lot of merge conflicts in your composer.json / composer.lock?

Try this composer plugin! It tracks all composer commands you execute on a project in a project file, so whenever there is a conflict with someone else's branch: Accept theirs or even better: check out their version and simply redo your changes on top of that!  

## Installation

`composer require serethix/composer-command-log` or if you want to run it globally run `composer global require serethix/composer-command-log`

In case you encounter an error with this plugin you can run every command with the `--no-plugins` option to temporarily disable all installed plugins.

## Configuration
This Plugin can be configured either globally or on a per project base.

Currently you can specify these options:

```json
{
  "require": {
    "serethix/composer-command-log": "^1.0"
  },
  "config": {
    "command-logfile": "composer-command.log",
    "command-to-log": [
      "require",
      "remove",
      "update",
      "upgrade",
      "run-script",
      "exec",
      "dumpautoload",
      "dump-autoload",
      "config"
    ]
  }
}
```

`command-logfile` specifies the name and path relative to your project directory.

By altering the `command-to-log` config you can add other commands to the list of commands you want to log and therefore be able to replay later.

This are also the default values and you only need to provide them in case you need a change on them.

# Contributing

Pull requests for new features, bug fixes and suggestions are welcome.

Before creating new features have a look at the already planned features and WIP features.

## Planned features

- Utilizing the `tag` property that is saved together with the commands information
  - include/exclude filter for tags
- Time based filter
- Ask for confirmation on every command before executing (like `git add -p` modes)
  - **a**ccept all
  - **y**es
  - **n**o
  - **l**ater
- Flock on log file during processing.

## WIP features
_Project just started, so what are you thinking? ^^_

# License
[GNU General Public License v3.0](https://github.com/SerethiX/ComposerCommandLog/blob/master/LICENSE)

# Thanks go to
Rafael Dohms for his [blog post](http://blog.doh.ms/2016/11/28/solving-conflicts-in-composer-lock/) that inspired the creation of this composer plugin.
