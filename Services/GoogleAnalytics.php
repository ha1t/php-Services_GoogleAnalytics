<?php
class Services_GoogleAnalytics
{
    const LOGIN_URL = 'https://www.google.com/accounts/ClientLogin';
    const API_URL = 'https://www.google.com/analytics/feeds/data?';

    private $auth_code;
    private $profile_id;
    
    private $end_date;
    private $start_date;
    
    public function __construct($email, $passwd) {
        //default the start and end date
        $this->start_date = date('Y-m-d', strtotime('-1 month'));
        $this->end_date = date('Y-m-d');
        $this->auth_code = $this->getAuthCode($email, $passwd);
    }
    
    public function setProfile($id)
    {
        if (!preg_match('/^ga:\d{1,10}/', $id)) {
            throw new Exception('Invalid GA Profile ID set. The format should ga:XXXXXX, where XXXXXX is your profile number');
        }
        $this->profile_id = $id; 
    }
    
    /**
    * Sets the date range
    * 
    * @param string $start_date (YYYY-MM-DD)
    * @param string $end_date   (YYYY-MM-DD)
    */
    public function setDateRange($start_date, $end_date) {
        //validate the dates
        if (!preg_match('/\d{4}-\d{2}-\d{2}/', $start_date)) {
            throw new Exception('Format for start date is wrong, expecting YYYY-MM-DD format');
        }
        if (!preg_match('/\d{4}-\d{2}-\d{2}/', $end_date)) {
            throw new Exception('Format for end date is wrong, expecting YYYY-MM-DD format');
        }
        if (strtotime($start_date) > strtotime($end_date)) {
            throw new Exception('Invalid Date Range. Start Date is greated than End Date');
        }
        $this->start_date = $start_date;
        $this->end_date = $end_date;
    }
    
    /**
    * Retrieve the report according to the properties set in $properties
    *
    * @param array $properties
    * @return array
    */
    public function getReport($properties = array()) {
        if (!count($properties)) {
            throw new Exception('getReport requires valid parameter to be passed');
        }

        $properties['ids'] = $this->profile_id;
        $properties['start-date'] = $this->start_date;
        $properties['end-date'] = $this->end_date;

        $xml = $this->callAPI(self::API_URL . http_build_query($properties));

        $results = array();
        $dom = new DOMDocument();
        $dom->loadXML($xml);
        $entries = $dom->getElementsByTagName('entry');
        foreach ($entries as $entry) {
            $mets = array();

            $dimensions = $entry->getElementsByTagName('dimension');
            foreach ($dimensions as $dimension) {
                $name = $dimension->getAttribute('name');
                $mets[$name] = $dimension->getAttribute('value');
            }

            $metrics = $entry->getElementsByTagName('metric');
            foreach ($metrics as $metric) {
                $name = $metric->getAttribute('name');
                $mets[$name] = $metric->getAttribute('value');
            }
            $results[] = $mets;
        }
        return $results;
    }
    
    /**
    * Retrieve the list of Website Profiles according to your GA account
    *
    * @param none
    * @return array
    */
    public function getWebsiteProfiles() {

        // make the call to the API
        $response = $this->callAPI('https://www.google.com/analytics/feeds/accounts/default');

        //parse the response from the API using DOMDocument.
        $dom = new DOMDocument();
        $dom->loadXML($response);
        $entries = $dom->getElementsByTagName('entry');
        foreach($entries as $entry){
            $tmp['title'] = $entry->getElementsByTagName('title')->item(0)->nodeValue;
            $tmp['id'] = $entry->getElementsByTagName('id')->item(0)->nodeValue;
            foreach($entry->getElementsByTagName('property') as $property){
                if (strcmp($property->getAttribute('name'), 'ga:accountId') == 0){
                    $tmp["accountId"] = $property->getAttribute('value');
                }    
                if (strcmp($property->getAttribute('name'), 'ga:accountName') == 0){
                    $tmp["accountName"] = $property->getAttribute('value');
                }
                if (strcmp($property->getAttribute('name'), 'ga:profileId') == 0){
                    $tmp["profileId"] = $property->getAttribute('value');
                }
                if (strcmp($property->getAttribute('name'), 'ga:webPropertyId') == 0){
                    $tmp["webProfileId"] = $property->getAttribute('value');
                }
            }
            $profiles[] = $tmp;
        }
        return $profiles;
    }
    
    private function callAPI($url)
    {
        $options = array(
            'http' => array(
                'method' => 'GET',
                'header' => implode("\r\n", array(
                    'Content-Type: application/x-www-form-urlencoded',
                    'Content-Length: 0',
                    "Authorization: GoogleLogin auth={$this->auth_code}"
                )),
            )
        );
        $xml = file_get_contents($url, false, stream_context_create($options));
        if (!$xml) {
            throw new Exception('Failed to get a valid XML from Google Analytics API service');
        }
        return $xml;
    }
        
    private function getAuthCode($email, $passwd)
    {  
        $data = array(
            'accountType' => 'GOOGLE',
            'Email' => $email,
            'Passwd' => $passwd,
            'service' => 'analytics',
            'source' => 'askaboutphp-v01'
        );

        $data = http_build_query($data);
        $options = array(
            'http' => array(
                'method' => 'POST',
                'content' => $data,
                'header' => implode("\r\n",
                array(
                    'Content-Type: application/x-www-form-urlencoded',
                    'Content-Length: ' . strlen($data),
                    'User-Agent: file_get_contents',
                )
            ),
        ));
        $response = file_get_contents(self::LOGIN_URL, false, stream_context_create($options));
        if (!$response) {
            throw new Exception('Failed to authenticate, please check your email and password.');
        }

        //process the response;
        preg_match('/Auth=(.*)/', $response, $matches);
        if (isset($matches[1])) {
            return $matches[1];
        }
    }
}
