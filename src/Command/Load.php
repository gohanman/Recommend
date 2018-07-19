<?php

namespace COREPOS\Recommend\Command;

use COREPOS\Recommend\Driver\Driver;
use GraphAware\Neo4j\Client\ClientBuilder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\ProgressBar;
use \Exception;

class Load extends Command
{
    private $driver;
    private $neo;

    public function __construct(Driver $driver, array $neo4j)
    {
        $this->driver = $driver;
        $this->neo = $neo4j;
        parent::__construct();
    }

    protected function configure()
    {
        $this->setName('load')
            ->setDescription('Load purchases data')
            ->setHelp('Do ETL for purchases data within a given date range')
            ->addArgument('start', InputArgument::REQUIRED, 'Start Date (inclusive, YYYY-MM-DD)')
            ->addArgument('end', InputArgument::REQUIRED, 'End Date (inclusive, YYYY-MM-DD)');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $n4j = $this->getN4J();
        $progress = new ProgressBar($output);

        $start = $input->getArgument('start');
        if (!preg_match('/^\d\d\d\d-\d\d-\d\d$/', $start)) {
            throw new Exception("Invalid start date: $start");
        }
        $end = $input->getArgument('end');
        if (!preg_match('/^\d\d\d\d-\d\d-\d\d$/', $end)) {
            throw new Exception("Invalid end date: $end");
        }

        foreach ($this->driver->getTransactions($start, $end) as $t) {
            $itemName = $this->driver->getItem($t['upc']);
            $this->createPerson($n4j, $t['customer']);
            $this->createItem($n4j, $t['upc'], $itemName);
            $this->createLink($n4j, $t['customer'], $t['upc'], $t['frequency']);
            $progress->advance();
        }
    }

    private function getN4J()
    {
        $uri = 'bolt://';
        if (isset($this->neo['user'])) {
            $uri .= $this->neo['user'];
        }
        if (isset($this->neo['password'])) {
            $uri .= ':' . $this->neo['password'];
        }
        if ($uri !== 'bolt://') {
            $uri .= '@';
        }
        $uri .= isset($this->neo['host']) ? $this->neo['host'] : 'localhost';

        $n4j = ClientBuilder::create()
            ->addConnection('bolt', $uri)
            ->build();

        return $n4j;
    }

    private function createPerson($n4j, $id)
    {
        $n4j->run('MERGE (n:Person { id: $id })', ['id' => $id]);
    }

    private function createItem($n4j, $upc, $name)
    {
        $result = $n4j->run('
            MERGE (n:Item { upc: $upc })
            RETURN n.name', ['upc' => $upc]);
        $record = $result->getRecord();
        if ($record->value('n.name') != $name) {
            $n4j->run('MATCH (n:Item) 
                WHERE n.upc = $upc
                SET n.name=$name', ['upc'=>$upc, 'name'=>$name]);
        }
    }

    private function createLink($n4j, $id, $upc, $weight)
    {
        $n4j->run('
            MATCH (p:Person { id: $id}), (i:Item { upc: $upc })
            MERGE (p)-[r:PURCHASED]->(i)
        ', ['id'=>$id, 'upc'=>$upc]);
        $n4j->run('
            MATCH (p:Person)-[r:PURCHASED]->(i:Item)
            WHERE p.id=$id AND i.upc=$upc
            SET r.weight=r.weight + $weight
        ', ['id'=>$id, 'upc'=>$upc, 'weight'=>$weight]);
    }
}

