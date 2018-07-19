<?php

namespace COREPOS\Recommend\Util;
use \Exception;

class Config
{
    private $config = array();

    public function __construct()
    {
        $file = __DIR__ . '/../../config.json';
        if (!file_exists($file)) {
            $real = realpath(__DIR__ . '/../../') . DIRECTORY_SEPARATOR . 'config.json';
            throw new Exception("Missing configuration file {$real}");
        }

        $content = file_get_contents($file);
        $json = json_decode($content, true);
        if ($json === null || !is_array($json)) {
            $real = realpath($file);
            throw new Exception("Invalid json in configuration file {$real}");
        }

        $this->config = $json;
    }

    public function setConfig(array $config)
    {
        $this->config = $config;
    }

    public function getDriver()
    {
        if (!isset($this->config['driver']) || !isset($this->config['driver']['name'])) {
            throw new Exception("No driver specified in configuration file");
        }

        $class = $this->config['driver']['name'];
        if (!class_exists($class)) {
            throw new Exception("Configured driver '{$class}' does not exist");
        }

        $options = isset($this->config['driver']['options']) ? $this->config['driver']['options'] : [];

        return new $class($options);
    }

    public function getNeo4j()
    {
        return isset($this->config['neo4j']) ? $this->config['neo4j'] : [];
    }
}

