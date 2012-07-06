<?php

error_reporting(E_ALL);
/**
 * sense dashboard - Sensor.php
 *
 * This file is part of sense dashboard.
 *
 * @author Remi Appels <remi@sense-os.nl>
 */

if (0 > version_compare(PHP_VERSION, '5')) {
    die('This file was made for PHP 5');
}

/**
 * The class Sensor handels all the sensor actions
 *
 * @access public
 * @author Remi Appels <remi@sense-os.nl>
 */
class Sensor
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
    private $type = null;
	
	/**
     * @access private
     * @var string
     */
    private $device_type = null;
	
	/**
     * @access private
     * @var int
     */
    private $data_type_id = null;
	
	/**
     * @access private
     * @var string
     */
    private $pager_type = null;
	
	/**
     * @access private
     * @var string
     */
    private $display_name = null;
	
	/**
     * @access private
     * @var string
     */
    private $data_type = null;
	
	/**
     * @access private
     * @var string
     */
    private $data_structure = null;
	
	
	
	// --- OPERATIONS ---

	public function Sensor(stdClass $data, Api $api){
		$this->id = $data->{'id'};
		$this->name = $data->{'name'};
		$this->type = $data->{'type'};
		$this->device_type = $data->{'device_type'};
		if(isset($data->{'data_type_id'}))
			$this->data_type_id = $data->{'data_type_id'};
		$this->pager_type = $data->{'pager_type'};
		$this->display_name = $data->{'display_name'};
		$this->data_type = $data->{'data_type'};
		$this->data_structure = $data->{'data_structure'};
		$this->api = $api;
	}
	
	public function getID(){
		return $this->id;
	}
	
	public function getName(){
		return $this->name;
	}
	
	public function getDeviceType(){
		return $this->device_type;
	}
	
	/**
     * This method will update an existing sensor.
     *
     * @access public
     * @param  string name
     * @param  string displayName
     * @param  string deviceType
     * @param  string pagerType
     * @param  string dataType
     * @param  string dataStructure
     * @return mixed
     */
    public function update($name, $displayName, $deviceType, $pagerType, $dataType, $dataStructure)
    {
		return $this->api->updateSensorDescription($this->getID(), $name, $displayName, $deviceType, $pagerType, $dataType, $dataStructure);
    }
	
	/**
     * This method will delete a sensor. If the current user is the owner of the sensor then the sensor will be removed from the current user and all other users. If the current user is not owner of the sensor then access to the sensor will be removed for this user.
     *
     * @access public
     * @return mixed
     */
    public function delete()
    {
		return $this->api->deleteSensor($this->getID());
    }
	
	/**
     * This method will return a list of sensor data. The maximum amount of data points that can be retrieved at once are 1000 items.
     *
     * @access public
     * @param  int page
     * @param  int perPage
     * @param  Date startDate
     * @param  Date endDate
     * @param  Date date
     * @param  int next
     * @param  Boolean last
     * @param  string sort
     * @param  Boolean total
     * @return json object (max 1000 items)
     */
    public function getData( $page, $perPage, $startDate, $endDate, $date, $next, $last, $sort, $total, $interval = 0)
    {
    	return $this->api->listSensorData($this->getID(), $page, $perPage, $startDate, $endDate, $date, $next, $last, $sort, $total, $interval);
	}
	
	/**
     * With this method sensor data can be uploaded. The uploaded data can either be a single value or an array.
     *
     * @access public
     * @param  string value
     * @param  Date date
     * @return mixed
     */
    public function updateData($value, $date)
    {
    	return $this->api->updateSensorSpecificData($this->getID(), $value, $date);
	}
	
	/**
     * This method deletes a data point
     *
     * @access public
     * @param  int dataID
     * @return mixed
     */
    public function deleteData($dataID)
    {
		return $this->api->deleteSensorData($this->getID(), $dataID);
    }
	
	/**
     * With this method sensor data can be uploaded at once for different sensors. The uploaded data can either be a single value or an array.
     *
     * @access public
     * @param  string json
     * @return mixed
     */
    public function uploadDataAsJson( $json)
    {
		return $this->api->uploadSensorData($json);
    }
	
	/**
     * This method returns the details of the device to witch the sensor is connected.
     *
     * @access public
     * @return Device
     */
	public function getMyDevice()
    {
		return $this->api->readParentDevice($this->getID());
    }
	
	/**
     * The method returns the details of the environment of this sensor.
     *
     * @access public
     * @return mixed
     */
    public function getEnvironment()
    {
		return $this->api->readSensorEnvironment($this->getID());
    }
	
	/**
     * This method will add a user to a sensor, giving the user access to the sensor and data. Only the owner of the sensor is able to upload data, mutate sensors and add users to their sensor. To add a user at least a username or user_id must be specified.
     *
     * @access public
     * @param  int userID
     * @param  string username
     * @return mixed
     */
    public function addSharredUser($userID, $userID)
    {
		return $this->api->addSharredUser($this->getID(), $userID, $userID);
    }
	
	/**
     * This method removes a users from a sensor, which removes the access to the sensor for this user.
     *
     * @access public
     * @param  int userID
     * @return mixed
     */
    public function removeSharredUser($userID)
    {
		return $this->api->removeSharredUser($this->getID(), $userID);
    }
	
	/**
     * This method connects a sensor to the sensor selected with <sensor_id>. The type of the selected sensor will be automatically set to 2 (virtual sensor).
     *
     * @access public
     * @param  int connectedSensorID
     * @return mixed
     */
    public function connectSensor($connectedSensorID)
    {
		return $this->api->connectSensor($this->getID(), $connectedSensorID);
    }
	
	 /**
     * This method removes a sensor from the parent sensor. If the parent sensor does not have any sensors that it uses, its type will automatically be set to 0. If this parent sensor is also a service, then the connected sensor will also be disconnected from the service.
     *
     * @access public
     * @param  int connectedSensor
     * @return mixed
     */
    public function removeConnectedSensor($connectedSensor)
    {
		return $this->api->removeConnectedSensor($this->getID(), $connectedSensor);
    }
	
	/**
     * This method disconnects the parent sensor from the service. The service will be stopped if it's not used by other sensors.
     *
     * @access public
     * @param  int serviceID
     * @return mixed
     */
    public function disconnectFromService( $serviceID)
    {
		return $this->api->disconnectFromService($this->getID(), $serviceID);
    }
	
	
	
} /* end of class Sensor */

?>