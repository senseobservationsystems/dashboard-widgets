<?php

error_reporting(E_ALL);
/**
 * sense dashboard - Data.php
 *
 * This file is part of sense dashboard.
 *
 * @author Remi Appels <remi@sense-os.nl>
 */

if (0 > version_compare(PHP_VERSION, '5')) {
    die('This file was made for PHP 5');
}

/**
 * The class Data handels all the data actions
 *
 * @access public
 * @author Remi Appels <remi@sense-os.nl>
 */
class Data
{
    // --- ASSOCIATIONS ---


    // --- ATTRIBUTES ---


	/**
     * @access private
     * @var Api
     */
    private $api = null;
	
    /**
     * @access private
     * @var int
     */
    private $id = null;
	
	/**
     * @access private
     * @var int
     */
    private $sensorId = null;
	
	/**
     * @access private
     * @var string
     */
    private $value = null;
	
	/**
     * @access private
     * @var timestamp
     */
    private $date = null;
	
	/**
     * @access private
     * @var int
     */
    private $week = null;
	
	/**
     * @access private
     * @var int
     */
    private $month = null;
	
	/**
     * @access private
     * @var int
     */
    private $year = null;

	// --- OPERATIONS ---
	public function Data(stdClass $data, Api $api){
		$this->id = $data->{'id'};
		$this->sensorId = $data->{'sensor_id'};
		$this->value = $data->{'value'};
		$this->date = $data->{'date'};
		$this->week = $data->{'week'};
		$this->month = $data->{'month'};
		$this->year = $data->{'year'};
		$this->api = $api;
	}
	
	public function getID(){
		return $this->id;
	}
	
	public function getSensorId(){
		return $this->sensorId;
	}
	
	public function getValue(){
		return $this->value;
	}
	
	public function getDate(){
		return $this->date;
	}
	
	public function getWeek(){
		return $this->week;
	}
	
	public function getMonth(){
		return $this->month;
	}
	
	public function getYear(){
		return $this->year;
	}
	
	/**
     * This method deletes a data point
     *
     * @access public
     * @return mixed
     */
    public function delete()
    {
		return $this->api->deleteSensorData($this->getSensorId(), $this->getID());
    }
	
	/**
     * The response header will contain a location header with the location of the file.
     *
     * @access public
     * @return json object
     */
    public function getFileLocation()
    {
		return $this->api->getFileLocation($this->getSensorId(), $this->getID());
    }
	
	/**
     * This method deletes the file that is uploaded and stored under the name given in this sensor data value.
     *
     * @access public
     * @return mixed
     */
    public function deleteFile()
    {
		return $this->api->deleteFile($this->getSensorId(), $this->getID());
    }
	
	
} /* end of class Sensor */

?>