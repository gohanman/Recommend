<?php

namespace COREPOS\Recommend\Driver;
use \Exception;

/**
 * @class Script
 *
 * The Script driver simply runs configured commands to
 * extract data from the ETL source. It's intended for
 * situations where accessing the data directly through
 * PHP is difficult and/or the user would prefer to work
 * in another language.
 *
 * This driver requires the following options in config.json:
 *  - getTransactions: the script to run when retrieving transaction
 *      data
 *  - getItem: the script to run when retrieving item names
 *
 * Using full paths to scripts is probably a good idea.
 *
 * Spec: getTransactions script:
 *  - takes start and end dates as command line arguments
 *  - writes JSON to STDOUT
 *  - each line of output should contain its own JSON object
 *  - object must have entries for
 *    - upc: unique item identifier
 *    - customer: unique customer identifier
 *    - frequency: number of times customer purchased the item
 *          during the provided date range
 *
 * Spec: getItem script:
 *  - takes UPC as a command line argument
 *  - writes on line to STDOUT containing the item's name
 *      or descriptor
 *
 */
class Script implements Driver
{
    private $getItem;
    private $getTransactions;

    public function __construct(array $options)
    {
        if (!isset($options['getTransactions'])) {
            throw new Exception("Driver option 'getTransactions' is required");
        }
        if (!isset($options['getItem'])) {
            throw new Exception("Driver option 'getItem' is required");
        }

        $this->getTransactions = $options['getTransactions'];
        $this->getItem = $options['getItem'];
    }

    public function getTransactions($startDate, $endDate)
    {
        $cmd = escapeshellcmd($this->getTransactions)
            . ' ' . escapeshellarg($startDate)
            . ' ' . escapeshellarg($endDate);
        $output = $this->runCommand($cmd);

        $output = $this->runCommand($cmd);
        foreach ($output as $line) {
            $json = json_decode($line, true);
            if ($json === null
                || !is_array($json)
                || !isset($json['upc'])
                || !isset($json['customer'])
                || !isset($json['frequency'])
            ) {
                throw new Exception(
                    "getTransactions script returned invalid line\n"
                    . "Command: {$cmd}\n"
                    . "Problem line: {$line}"
                );
            }

            yield $json;
        }
    }

    public function getItem($upc)
    {
        $cmd = escapeshellcmd($this->getItem)
            . ' ' . escapeshellarg($upc);

        $output = $this->runCommand($cmd);

        return $output[0];
    }

    private function runCommand($cmd)
    {
        exec($cmd, $output, $ret);
        if ($ret != 0) {
            throw new Exception(
                "getItem script returned an error\n"
                . "Command {$cmd}\n"
                . "Output: {$output}"
            );
        }
        if (count($output) == 0 || $output[0] == '') {
            throw new Exception("
                getItem script returned nothing\n"
                . "Command {$cmd}\n"
            );
        }

        return $output;
    }
}

