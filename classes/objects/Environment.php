<?php

error_reporting(E_ALL);
/**
 * sense dashboard - Environment.php
 *
 * This file is part of sense dashboard.
 *
 * @author Remi Appels <remi@sense-os.nl>
 */

if (0 > version_compare(PHP_VERSION, '5')) {
    die('This file was made for PHP 5');
}

/**
 * The class Environment handels all the environment actions
 *
 * @access public
 * @author Remi Appels <remi@sense-os.nl>
 */
class Environment
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
     * @var string
     */
    private $name = null;
	
	/**
     * @access private
     * @var int
     */
    private $floors = null;
	
	/**
     * @access private
     * @var string
     */
    private $gps_outline = null;
	
	/**
     * @access private
     * @var string
     */
    private $position = null;
	
	/**
     * @access private
     * @var date
     */
    private $date = null;
	
	
	
	
	// --- OPERATIONS ---

	public function Environment(stdClass $data, Api $api ){
		$this->id = $data->{'id'};
		$this->name = $data->{'name'};
		$this->floors = $data->{'floors'};
		$this->gps_outline = $data->{'gps_outline'};
		$this->position = $data->{'position'};
		$this->date = $data->{'date'};
		$this->api = $api;
	}
	
	public function getID(){
		return $this->id;
	}
	
	public function getName(){
		return $this->name;
	}
	
	public function getFloors(){
		return $this->floors;
	}
	
	public function getGpsOutline(){
		return $this->gps_outline;
	}
	
	public function getPosition(){
		return $this->position;
	}
	
	public function getDate(){
		return $this->date;
	}
	
	/**
     * This method updates an environment. Only the fields that are send will be updated.
     *
     * @access public
     * @param  string name
     * @return mixed
     */
    public function update($name)
    {
		return $this->api->updateEnvironment($this->getID(), $name);
    }
	
	/**
     * This method list the sensors which are connected to this environment.
     *
     * @access public
     * @return mixed
     */
    public function getSensors()
    {
		return $this->api->listEnvironmentSensors($this->getID());
    }
	
	
	/**
     * This method removes the selected sensor from the selected environment.
     *
     * @access public
     * @param  int sensorID
     * @return mixed
     */
    public function removeSensor($sensorID)
    {
		return $this->api->removeSensorFromEnvironment($this->getID(), $sensorID);
    }
	
	/**
     * The method adds a sensor to an environment. To connect an individual sensor a sensor object with only the sensor id can be given and to connect a list of sensors a sensors object with an array of sensor ids can be given.
     *
     * @access public
     * @param  Array sensorIds
     * @return mixed
     */
    public function addSensors($sensorIds)
    {
    	return $this->api->addSensorsToEnvironment($this->getID(), $sensorIds);
    }

	
} /* end of class Environment */

?>