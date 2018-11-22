<?php
/**
 * Created by PhpStorm.
 * User: Nils
 * Date: 22.11.2018
 * Time: 03:43
 */

namespace SerethiX\ComposerCommandLogPlugin;


use Composer\Command\BaseCommand;
use Composer\Composer;
use Symfony\Component\Console\Command\LockableTrait;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;

class LoggingReplayCommand extends BaseCommand
{
    use LockableTrait;

    /**
     * @var string
     */
    protected $logfile;

    public function __construct(Composer $composer, $name = null)
    {
        $this->setComposer($composer);

        $config        = $composer->getConfig();
        $this->logfile = CommandLoggingPlugin::getConfiguration($config, CommandLoggingPlugin::CONFIG_LOG_FILE);

        parent::__construct($name);
    }

    protected function configure()
    {
        $this->setName('log-replay');

        $this->setDescription("Plugin serethix/composer-command-log: Read and reapply composer commands.");

        $this->setHelp("Intended usage: during your development you may have to deal with merge conflicts in the <info>composer.json</info> and/or <info>composer.lock</info> file\n"
                       . "This Plugin logs all important commands into a logfile (<info>{$this->logfile}</info>) you should commit to your VCS.\n"
                       . "Now you are rebasing or your merge request conflicts with another ones changes to composer.json or composer.lock.\n"
                       . "Since you logged all your changes you made on a command basis, you can \"accept their\" and redo your changes and commit the result.\n"
                       . "\n"
                       . "When doing a rebase or merging into a topic branch I wouldn't clear the file (by using --keep), only directly before the merge into the final branch.\n"
        );

        $this->addOption('keep',
                         null,
                         InputOption::VALUE_NONE,
                         'Keep log entries when replaying them.');

        $this->addOption('skip-duplicates',
                         null,
                         InputOption::VALUE_NONE,
                         'During a replay duplicate entries are all executed. In some cases it might be better to ignore duplicates, this depends on your log.');
    }

    protected function interact(InputInterface $input, OutputInterface $output)
    {
        /** @var QuestionHelper $helper */
        $helper = $this->getHelper('question');

        if (!$input->getOption('keep')) {
            $question = new ConfirmationQuestion("<question>You called this command without the --keep option, are you sure you want to delete all selected log entries? (Y|n):</question> ",
                                                 true,
                                                 '/^(y|j)/i');

            $result = $helper->ask($input, $output, $question);

            $input->setOption('keep', !$result);
            //$output->writeln("<info>Keeping old entries!</info>");
        }
    }


    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $fileContent = trim(file_get_contents($this->logfile));

        if (empty($fileContent)) {
            $output->writeln("<error>No entries found in {$this->logfile}</error>");

            return;
        }

        $entries        = preg_split('/\\r\\n|\\r|\\n/', $fileContent);
        $successEntries = [];
        $failedEntries  = [];

        $app = $this->getApplication();

        $duplicate = [];

        foreach ($entries as $key => $value) {
            $this->lock(CommandLoggingPlugin::CONFIG_COMMAND_MODE, true);

            $parts = explode(':', $value, 2);

            $output->writeln(print_r($parts, true), OutputInterface::VERBOSITY_VERY_VERBOSE);
            $output->writeln("<info>Replaying version {$parts[0]} ...</info>" . PHP_EOL);
            $output->writeln("JSON: {$parts[1]}", OutputInterface::VERBOSITY_VERY_VERBOSE);

            $savedData = json_decode($parts[1], true);

            $inputArray = $this->getInputArray($savedData);

            $id = md5(print_r($inputArray, true));

            if (isset($duplicate[$id])) {
                $duplicateString = implode(', ', $duplicate[$id]);

                if ($input->getOption('skip-duplicates')) {
                    $output->writeln("Skipping version {$parts[0]}, it's a duplicate of version {$duplicateString}");
                    continue;
                } else {
                    $output->writeln("Version {$parts[0]} is a duplicate of version {$duplicateString}",
                                     OutputInterface::VERBOSITY_VERBOSE);
                }
            }
            $duplicate[$id][] = $parts[0];

            $replayInput = new ArrayInput($inputArray);

            try {
                $result = $app->doRun($replayInput, $output);
            } catch (\Throwable $exception) {
                $result = 255;
                $output->writeln("Encountered an error when replaying version {$parts[0]}: {$exception->getMessage()}");
            } finally {
                if ($result !== 255) {
                    $successEntries[$key] = $value;
                } else {
                    $output->writeln("The return value of version {$parts[0]} is not zero: {$result}");
                    $failedEntries[$key] = $value;
                }
            }

            $output->writeln(PHP_EOL);

            $this->release();
        }

        if ($input->getOption('keep') === true) {
            $output->writeln("<info>Keeping all log entries in {$this->logfile}</info>");

            $writeToFile = $entries;
        } else {
            $output->writeln("<comment>Removing successful replayed entries from  {$this->logfile}</comment>");

            $writeToFile = $failedEntries;
        }

        $entryString = trim(implode(PHP_EOL, $writeToFile));

        if (!empty($entryString)) {
            $entryString .= PHP_EOL;
        }

        file_put_contents($this->logfile, $entryString);

        $output->writeln("<info>Done!</info>");
    }

    /**
     * @param array $savedData
     *
     * @return array
     */
    protected function getInputArray(array $savedData): array
    {
        $result = $savedData['arguments'];

        foreach ($savedData['options'] as $option => $value) {
            $result['--' . $option] = $value;
        }

        $result = array_filter($result,
            function ($value): bool {
                return $value !== null && $value !== false;
            });

        return $result;
    }
}
