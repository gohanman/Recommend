<?php

namespace COREPOS\Recommend\Driver;
use COREPOS\common\SQLManager;
use \Exception;

/**
 * @class CoreDriver
 *
 * This is the CORE-POS driver. It will extract requested
 * data from a standard CORE-POS schema.
 *
 * This driver requires the following options in config.json:
 *  - fanniePath: path to the CORE Office installation
 *
 * This driver also accepts these config.json options:
 *  - exclude:
 *    - members: list of accounts to exclude
 *    - types: list of account types to exclude
 *
 * Note: if this tool isn't on the same server as CORE Office
 * just copy the backend's config.php to somewhere on this server.
 * This driver needs that file to locate & connect to CORE's
 * database.
 */
class CoreDriver implements Driver
{
    private $dbc = null;
    private $opdb = 'core_op';
    private $excludeTypes = array();
    private $excludeIDs = array();

    public function __construct(array $options)
    {
        if (!isset($options['fanniePath'])) {
            throw new Exception("Driver requires option 'fanniePath'");
        }

        if (!file_exists($options['fanniePath'] . DIRECTORY_SEPARATOR . 'config.php')) {
            $real = realpath($options['fanniePath']) . DIRECTORY_SEPARATOR . 'config.php';
            throw new Exception("Fannie config not found ({$real})");
        }

        if (isset($options['exclude']) && isset($options['exclude']['types'])) {
            $this->excludeTypes = $options['exclude']['types'];
        }
        if (isset($options['exclude']) && isset($options['exclude']['members'])) {
            $this->excludeIDs = $options['exclude']['members'];
        }

        include($options['fanniePath'] . DIRECTORY_SEPARATOR . 'config.php');
        $this->dbc = new SQLManager(
            $FANNIE_SERVER,
            $FANNIE_SERVER_DBMS,
            $FANNIE_ARCHIVE_DB,
            $FANNIE_SERVER_USER,
            $FANNIE_SERVER_PW
        );
        $this->opdb = $FANNIE_OP_DB;
    }

    public function getTransactions($startDate, $endDate)
    {
        $args = [$startDate, $endDate . ' 23:59:59'];
        list($inID, $args) = $this->dbc->safeInClause($this->excludeIDs, $args);
        list($inType, $args) = $this->dbc->safeInClause($this->excludeTypes, $args);
        $prep = $this->dbc->prepare("
            SELECT upc, card_no, SUM(quantity) AS qty, COUNT(*) AS rings
            FROM dlogBig
            WHERE tdate BETWEEN ? AND ?
                AND trans_type='I'
                AND card_no NOT IN ({$inID})
                AND memType NOT IN ({$inType})
            GROUP BY upc, card_no
            HAVING ABS(SUM(total)) > 0.005");
        $res = $this->dbc->execute($prep, $args);
        while ($row = $this->dbc->fetchRow($res)) {
            $frequency = floor($row['qty']) == $row['qty'] ? $row['qty'] : $row['rings'];

            yield [
                'upc' => $row['upc'],
                'customer' => $row['card_no'],
                'frequency'=>$frequency
            ];
        }
    }

    public function getItem($upc)
    {
        $prep = $this->dbc->prepare("
            SELECT brand, description
            FROM " . $this->opdb . $this->dbc->sep() . "products
            WHERE upc=?");
        $row = $this->dbc->getRow($prep, [$upc]);
        $name = 'Unknown';
        if ($row) {
            $name = $row['description'];
            if ($row['brand']) {
                $name = $row['brand'] . ' ' . $name;
            }
        }

        return $name;
    }
}

