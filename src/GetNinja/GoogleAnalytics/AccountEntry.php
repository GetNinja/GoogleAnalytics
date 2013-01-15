<?php

namespace GetNinja\GoogleAnalytics\Analytics;

class AccountEntry
{
    /**
     * Properties.
     *
     * @var Array
     */
    private $properties = array();

    /**
     * Create a new AccountEntry Object.
     *
     * @param Array $properties
     */
    public function __construct($properties)
    {
        $this->properties = $properties;
    }

    /**
     * toString function to return the name of the account
     *
     * @return String
     */
    public function __toString()
    {
        if (isset($this->properties['title'])) {
            return $this->properties['title'];
        }

        return;
    }

    /**
     * Get an associative array of the properties
     * and the matching values for the current result
     *
     * @return Array
     */
    public function getProperties()
    {
        return $this->properties;
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
            throw new Exception('No such function "'.$name.'"');
        }

        $name = lcfirst(preg_replace('/^get/', '', $name));

        $propertyKey = array_key_exists($name, $this->properties);
        if ($propertyKey) {
            return $this->properties[$propertyKey];
        }

        throw new Exception('No valid property called "'.$name.'"');
    }
}
