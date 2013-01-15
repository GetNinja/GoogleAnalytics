<?php

namespace GetNinja\GoogleAnalytics\Analytics;

use AccountEntry;
use Buzz\Browser;

class Analytics
{
    /**
     * Google Analytics Client Login URL.
     */
    const CLIENT_LOGIN_URL = 'https://www.google.com/accounts/ClientLogin';

    /**
     * Google Analytics Account Feed URL.
     */
    const ACCOUNT_FEED_URL = 'https://www.google.com/analytics/feeds/accounts/default';

    /**
     * Google Analytics Account Feed URL.
     */
    const REPORT_FEED_URL = 'https://www.google.com/analytics/feeds/data';

    /**
     * Interface Name.
     */
    const INTERFACE_NAME = 'GetNinja Google Analytics v0.1';

    /**
     * Dev Mode.
     */
    const DEV_MODE = false;

    /**
     * Http Client.
     *
     * @var Buzz\Browser
     */
    private $httpClient = new Buzz\Browser();

    /**
     * The auth token.
     *
     * @var String
     */
    private $authToken = null;

    /**
     * Account Entries Array.
     *
     * @var Array
     */
    private $accountEntries = array();

    /**
     * Account Root Parameters Array.
     *
     * @var Array
     */
    private $accountRootParameters = array();

    /**
     * Report Aggregate Metrics Array.
     *
     * @var Array
     */
    private $reportAggregateMetrics = array();

    /**
     * Report Root Parameters Array.
     *
     * @var Array
     */
    private $reportRootParameters = array();

    /**
     * Results Array.
     *
     * @var Array
     */
    private $reportRootParameters = array();

    /**
     * Create a new analytics instance.
     *
     * @param String $email
     * @param String $password
     * @param String $token
     */
	public function __construct($email, $password, $token = null)
    {
        if($token !== null) {
            $this->authToken = $token;
        } else {
            $this->authenticateUser($email, $password);
        }
    }

    /**
     * Return the auth token.
     *
     * @return String
     */
    public function getAuthToken()
    {
        return $this->authToken;
    }

    /**
     * Request account data from Google Analytics.
     *
     * @param  Int   $startIndex OPTIONAL: Start Index of results
     * @param  Int   $maxResults OPTIONAL: Max Results Returned
     * @return Array
     */
    public function requestAccountData($startIndex= 1, $maxResults = 20)
    {
        $response = $this->httpRequest($this->ACCOUNT_FEED_URL, array(
            'start-index' => $startIndex,
            'max-results' => $maxIndex
        ), 'GET', $this->generateAuthHeader());

        if (substr($response['code'], 0, 1) == '2') {
            return $this->accountObjectMapper($response['body']);
        } else {
            throw new Exception('GoogleAnalytics: Failed to request account data. Error: "'.strip_tags($response['body']).'"');
        }
    }

    /**
     * Request report data from Google Analytics.
     *
     * @param  String $reportId
     * @param  Array|String  $dimensions Google Analytics dimensions e.g. array('browser')
     * @param  Array         $metrics    Google Analytics metrics e.g. array('pageviews')
     * @param  Array         $sortMetric OPTIONAL: Dimension or dimensions to sort by e.g. array('-visits')
     * @param  String        $filter     OPTIONAL: Filter logic for filtering results
     * @param  String        $startDate  OPTIONAL: Start of reporting period
     * @param  String        $endDate    OPTIONAL: End of reporting period
     * @param  Int           $startIndex OPTIONAL: Start index of results
     * @param  Int           $maxResults OPTIONAL: Max Results Returned
     * @return Array
     */
    public function requestReportData(
        $reportId,
        $dimensions,
        $metrics,
        $sortMetric = null,
        $filter = null,
        $startDate = null,
        $endDate = null,
        $startIndex = 1,
        $maxResults = 20
    ) {
        $parameters = array('ids' => 'ga:'.$reportId);

        // Format dimensions parameter
        if (is_array($dimensions)) {
            $dimensionsString = '';
            foreach ($dimensions as $dimension) {
                $dimensionsString .= ',ga:'.$dimension;
            }

            $parameters['dimensions'] = substr($dimensionsString, 1);
        } else {
            $parameters['dimensions'] = 'ga:'.$dimensions;
        }

        // Format metrics parameter
        if (is_array($metrics)) {
            $metricsString = '';
            foreach ($metrics as $metric) {
                $metricsString .= ',ga:'.$metric;
            }

            $parameters['metrics'] = substr($metricsString, 1);
        } else {
            $parameters['metrics'] = 'ga:'.$metrics;
        }

        // Check if sort metric is set and format it, set if not aleady
        if ($sortMetric == null && isset($parameters['metrics'])) {
            $parameters['sort'] = $parameters['metrics'];
        } elseif (is_array($sortMetric)) {
            $sortMetricString = '';
            foreach($sortMetric as $sortMetricValue) {
                if (substr($sortMetricValue, 0, 1) == '-') {
                    $sortMetricString .= ',-ga:'.substr($sortMetricValue, 1); // Descending
                } else {
                    $sortMetricString .= ',ga:'.$sortMetricValue; // Ascending
                }
            }

            $parameters['sort'] = substr($sortMetricString, 1);
        } else {
            if (substr($sortMetric, 0, 1) == '-') {
                $parameters['sort'] = '-ga:'.substr($sortMetric, 1); // Descending
            } else {
                $parameters['sort'] = 'ga:'.$sortMetric; // Ascending
            }
        }

        // Check if a filter is set
        if ($filter != null) {
            $filter = $this->processFilter($filter);
            if (!empty($filter)) {
                $parameters['filters'] = $filter;
            }
        }

        // Format start date
        if ($startDate == null) {
            $startDate = date('Y-m-d', strtotime('1 month ago'));
        }
        $parameters['start-date'] = $startDate;

        // Set remaining parameters
        $parameters['end-date']    = $endDate;
        $parameters['start-index'] = $startIndex;
        $parameters['max-results'] = $maxResults;
        $parameters['prettyprint'] = $this->devMode ? 'true' : 'false';

        $response = $this->httpRequest($this->reportDataUrl, $parameters, 'GET', $this->generateAuthHeader());

        // HTTP Response: 2xx
        if (substr($response['code'], 0, 1) == '2') {
            return $this->reportObjectMapper($response['body']);
        } else {
            throw new Exception('Google Analytics: Failed to request report data. Error: "'.strip_tags($response['body']).'"');
        }
    }

    /**
     * Process the filter string, convert to Google Analytics API format.
     *
     * @param  String $filter
     * @return String Properly formatted filter string
     */
    protected function processFilter($filter)
    {
        $validOperators = '(!~|=~|==|!=|>|<|>=|<=|=@|!@)';

        // Clean Duplicate Whitespace
        $filter = preg_replace('/\s\s+/', ' ', trim($filter));

        // Escape Google Analytics reserved characters.
        $filter = str_replace(array(',', ';'), array('\,', '\;'), $filter);

        // Clean up operators
        $filter = preg_replace(
            '/(&&\s*|\|\|s*|^)([a-z]+)(\s*'.$validOperators.')/i',
            '$1ga:$2$3',
            $filter
        );

        // Clean invalid quote characters
        $filter = preg_replace('/[\'\"]/i', '', $filter);

        //Clean up operators
        $filter = preg_replace(
            array('/\s*&&\s*/', '/\s*\|\|\s*/', '/\s*'.$validOperators.'\s*/'),
            array(';', ',', '$1'),
            $filter
        );

        if (strlen($filter) > 0) {
            return urlencode($filter);
        } else {
            return null;
        }
    }

    /**
     * Report Account Mapper to convert the XML to array of useful PHP objects
     *
     * @param  String $xmlString
     * @return Array of AccountEntry objects
     */
    protected function accountObjectMapper($xmlString)
    {
        $xml = simplexml_load_string($xmlString);

        $this->results = null;
        $results = array();

        $accountRootParameters = array();

        // Load root parameters
        $accountRootParameters['updated']          = strval($xml->updated);
        $accountRootParameters['generator']        = strval($xml->generator);
        $accountRootParameters['generatorVersion'] = strval($xml->generator->attributes());

        $openSearchResults = $xml->children('http://a9.com/-/spec/opensearchrss/1.0/');

        foreach ($openSearchResults as $key => $openSearchResult) {
            $accountRootParameters[$key] = intval($openSearchResult);
        }

        $googleResults = $xml->children('http://schemas.google.com/analytics/2009');
        
        $accountRootParameters['startDate'] = strval($googleResults->startDate);
        $accountRootParameters['endDate']   = strval($googleResults->endDate);

        // Load result entries
        foreach ($xml->entry as $entry) {
            $properties = array();
            foreach ($entry->children('http://schemas.google.com/analytics/2009')->property as $property) {
                $properties[str_replace('ga:', '', $property->attributes()->name)] = strval($property->attributes()->value);
            }

            $properties['title']   = strval($entry->title);
            $properties['updated'] = strval($entry->updated);

            $results[] = new AnalyticsEntry($properties);
        }

        $this->accountRootParameters = $accountRootParameters;
        $this->results = $results;

        return $results;
    }

    /**
     * Report Object Mapper to convert the XML to array of useful PHP objects
     *
     * @param  String $xmlString
     * @return Array of ReportEntry objects
     */
    protected function reportObjectMapper($xmlString)
    {
        $xml = simplexml_load_string($xmlString);

        $this->results = null;
        $results = array();

        $reportRootParameters = array();
        $reportAggregateParameters = array();

        // Load root parameters
        $reportRootParameters['updated']          = strval($xml->updated);
        $reportRootParameters['generator']        = strval($xml->generator);
        $reportRootParameters['generatorVersion'] = strval($xml->generator->attributes());

        $openSearchResults = $xml->children('http://a9.com/-/spec/opensearchrss/1.0/');

        foreach ($openSearchResults as $key => $openSearchResult) {
            $reportRootParameters[$key] = intval($openSearchResult);
        }

        $googleResults = $xml->children('http://schemas.google.com/analytics/2009');

        foreach ($googleResults->dataSource->property as $propertyAttributes) {
            $reportRootParameters[str_replace('ga:', '', $propertyAttributes->attributes()->name)] = strval($propertyAttributes->attributes()->value);
        }

        $reportRootParameters['startDate'] = strval($googleResults->startDate);
        $reportRootParameters['endDate']   = strval($googleResults->endDate);

        // Load result aggregate metrics
        foreach ($googleResults->aggregates->metric as $aggregateMetric) {
            $metricValue = strval($aggregateMetric->attributes()->value);

            // Check for float, or value with scientific notation
            if (preg_match('/^(\d+\.\d+)|(\d+E\d+)|\d+.\d+E\d+)$/', $metricValue)) {
                $reportAggregateMetrics[str_replace('ga:', '', $aggregateMetric->attributes()->name)] = floatval($metricValue);
            } else {
                $reportAggregateMetrics[str_replace('ga:', '', $aggregateMetric->attributes()->name)] = intval($metricValue);
            }
        }

        // Load result entries
        foreach ($xml->entry as $entry) {
            $metrics = array();
            foreach ($entry->children('http://schemas.google.com/analytics/2009')->metric as $metric) {
                $metricValue = strval($metric->attributes()->value);

                // Check for float, or value with scientific notation
                if (preg_match('/^(\d+\.\d+)|(\d+E\d+)|\d+.\d+E\d+)$/', $metricValue)) {
                    $metrics[str_replace('ga:', '', $metric->attributes()->name)] = floatval($metricValue);
                } else {
                    $metrics[str_replace('ga:', '', $metric->attributes()->name)] = intval($metricValue);
                }
            }

            $dimensions = array();
            foreach($entry->children('http://schemas.google.com/analytics/2009')->dimension as $dimension) {
                $dimensions[str_replace('ga:', '', $dimension->attributes()->name)] = strval($dimension->attributes()->value);
            }

            $results[] = new ReportEntry($metrics, $dimensions);
        }

        $this->reportRootParameters = $reportRootParameters;
        $this->reportAggregateMetrics = $reportAggregateMetrics;
        $this->results = $results;

        return $results;
    }

    /**
     * Authenticate Google Account with Google
     *
     * @param String $email
     * @param String $password
     */
    protected function authenticateUser($email, $password)
    {
        $postVariables = array(
            'accountType' => 'GOOGLE',
            'Email'       => $email,
            'Passwd'      => $password,
            'source'      => $this->interfaceName,
            'service'     => 'analytics'
        );

        $response = $this->httpRequest($this->ClientLoginUrl, $postVariables, 'POST');

        // Convert newline delimited variables into url format then import to array
        parse_str(str_replace(array("\n", "\r\n"), '&', $response['body']), $authToken);

        if (substr($response['code'], 0, 1) != '2' || !is_array($authToken) || empty($authToken['Auth'])) {
            throw new Exception('Google Analytics: Failed to authenticate user. Error: "'.strip_tags($response['body']).'"');
        }

        $this->authToken = $authToken['Auth'];
    }

    /**
     * Generate authentication token header for all requestes
     *
     * @return Array
     */
    protected function generateAuthHeader()
    {
        return array('Authorization: GoogleLogin auth='.$this->authToken);
    }

    /**
     * Perform http request
     * 
     *
     * @param Array  $data
     * @param String $method
     * @param Array  $headers
     */
    public function httpRequest($url, $data = null, $method = 'GET', $headers = null)
    {
        $repsonse = $this->httpClient($url, $data, $method, $headers);

        print_r($response); die();
    }

    /**
     * Get results
     *
     * @return Array
     */
    public function getResults()
    {
        if(is_array($this->results)) {
            return $this->results;
        }

        return;
    }

    /**
     * Get an aray of the metrics and the matching
     * aggregate values for the current result
     *
     * @return Array
     */
    public function getMetrics()
    {
        return $this->reportAggregateMetrics;
    }

    /**
     * Call method to find the matching root parameter or
     * aggregate metric to return
     *
     * @param  String $name name of function called
     * @return String
     * @throws Exception if not a valid parameter or aggregate
     * metric, or not a 'get' function
     */
    public function __call($name, $parameters)
    {
        if (!preg_match('/^get/', $name)) {
            throw new Exception('No such function "'.$name.'"');
        }

        $name = lcfirst(preg_replace('/^get/', '', $name));

        $parameterKey = array_key_exists($name, $this->reportRootParameters);
        if($parameterKey) {
            return $this->reportRootParameters[$parameterKey];
        }

        $aggregateMetricKey = array_key_exists($name, $this->reportAggregateMetrics);
        if($aggregateMetricKey) {
            return $this->reportAggregateMetrics[$aggregateMetricKey];
        }

        throw new Exception('No valid root parameter or aggregate metric called "'.$name.'"');
    }
}
