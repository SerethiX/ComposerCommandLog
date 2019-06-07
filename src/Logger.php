<?php
/**
 * Created by PhpStorm.
 * User: Nils
 * Date: 21.11.2018
 * Time: 21:11
 */

namespace SerethiX\ComposerCommandLogPlugin;


use Exception;
use Symfony\Component\Console\Input\InputInterface;

class Logger
{
    public const TIME_FORMAT = 'Y-m-d H:i:sP';

    protected const DEFAULT_TAG = 'default';

    /**
     * @var \SplFileObject
     */
    protected $file;

    /**
     * Logger constructor.
     *
     * @param string $fileName
     */
    public function __construct(string $fileName)
    {
        $this->file = new \SplFileObject($fileName, 'a');
    }

    /**
     * @param InputInterface $input
     * @param string         $tag
     *
     * @return string
     */
    public function log(InputInterface $input, string $tag = null): string
    {
        $data = [
            'timestamp' => (new \DateTime('now'))->format(self::TIME_FORMAT),
            'arguments' => $input->getArguments(),
            'options'   => $input->getOptions(),
            'tag'       => $tag ?? self::DEFAULT_TAG,
        ];

        $encoded = json_encode($data);
        $version = md5($encoded);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Failed to correctly encode the log entry: " . json_last_error_msg());
        }

        $written = $this->file->fwrite($version . ':' . $encoded . PHP_EOL);

        if ($written === null || $written < 1) {
            throw new Exception("There was an error writing to file {$this->file->getPathname()}");
        }

        return $version;
    }
}
