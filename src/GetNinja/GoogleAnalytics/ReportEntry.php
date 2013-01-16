<?php

namespace GetNinja\GoogleAnalytics;

class ReportEntry
{
    /**
     * Google Analytics Metrics.
     *
     * @var Array
     */
    private $metrics = array();

    /**
     * Google Analytics Dimensions.
     *
     * @var Array
     */
    private $dimensions = array();

    /**
     * Create a new ReportEntry Object.
     *
     * @param Array $properties
     */
    public function __construct($metrics, $dimensions)
    {
        $this->metrics = $metrics;
        $this->dimensions = $dimensions;
    }

    /**
     * toString function to return the name of the result
     * this is a concatenated string of the dimensions chosen
     *
     * @return String
     */
    public function __toString()
    {
        if (isset($this->dimensions)) {
            return implode(' ', $this->dimensions);
        }

        return;
    }

    /**
     * Get an associative array of the dimensions
     * and the matching values for the current result
     *
     * @return Array
     */
    public function getDimensions()
    {
        return $this->dimensions;
    }

    /**
     * Get an associative array of the metrics
     * and the matching values for the current result
     *
     * @return Array
     */
    public function getMetrics()
    {
        return $this->metrics;
    }

    /**
     * Call method to find a matching parameter to return
     *
     * @param $name String name of function called
     * @return String
     * @throws Exception if not a valid parameter, or not a 'get' function
     */
    public function __call($name, $parameters)
    {
        if (!preg_match('/^get/', $name)) {
            throw new \Exception('No such function "'.$name.'"');
        }

        $name = lcfirst(preg_replace('/^get/', '', $name));

        $metricKey = array_key_exists($name, $this->metrics);
        if ($metricKey) {
            return $this->metrics[$name];
        }

        $dimensionKey = array_key_exists($name, $this->dimensions);
        if ($dimensionKey) {
            return $this->dimensions[$name];
        }

        throw new \Exception('No valid metric or dimension called "'.$name.'"');
    }
}
