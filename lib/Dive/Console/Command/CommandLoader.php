<?php
 /*
 * This file is part of the Dive ORM framework.
 * (c) Steffen Zeidler <sigma_z@sigma-scripts.de>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Dive\Console\Command;

use Dive\Console\ConsoleException;

/**
 * Class CommandLoader
 * @author  Steffen Zeidler <sigma_z@sigma-scripts.de>
 */
class CommandLoader implements CommandLoaderInterface
{

    /**
     * @var array
     * keys: directories
     * values: class namespaces
     */
    protected $directories = array();


    /**
     * constructor
     */
    public function __construct()
    {
        $this->directories[__DIR__] = '\\Dive\\Console\\Command';
    }


    /**
     * @param  string $directory
     * @param  string $namespace
     * @throws \Dive\Console\ConsoleException
     */
    public function addDirectory($directory, $namespace = '')
    {
        if (!is_dir($directory)) {
            throw new ConsoleException("Directory '$directory' is not a directory!");
        }
        $this->directories[$directory] = $namespace;
    }


    /**
     * @param  string $commandName
     * @throws ConsoleException
     * @return Command
     */
    public function createCommand($commandName)
    {
        $commandClass = $this->getCommandClass($commandName);
        if (!$commandClass) {
            throw new ConsoleException("Could not load command '$commandName'!");
        }
        return new $commandClass;
    }


    /**
     * @param  string $commandName
     * @return null|string
     */
    public function getCommandClass($commandName)
    {
        $commandName = ucfirst($commandName);
        foreach ($this->directories as $directory => $namespace) {
            $iterator = new \DirectoryIterator($directory);
            foreach ($iterator as $entry) {
                $file = (string)$entry;
                if (!is_file($directory . '/' . $file)) {
                    continue;
                }
                if ($file == $commandName . '.php') {
                    return $namespace . '\\' . $commandName;
                }
                else if ($file == $commandName . 'Command.php') {
                    return $namespace . '\\' . $commandName . 'Command';
                }
            }
        }
        return null;
    }

}
