<?php

error_reporting(E_ALL);
/**
 * sense dashboard - Service.php
 *
 * This file is part of sense dashboard.
 *
 * @author Remi Appels <remi@sense-os.nl>
 */

if (0 > version_compare(PHP_VERSION, '5')) {
    die('This file was made for PHP 5');
}

/**
 * The class Service handels all the service actions
 *
 * @access public
 * @author Remi Appels <remi@sense-os.nl>
 */
class Service
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
     * @var Array
     */
    private $data_fields = null;
	
	
	
	// --- OPERATIONS ---

	public function Service(stdClass $data, Api $api ){
		$this->id = $data->{'id'};
		$this->name = $data->{'name'};
		$this->data_fields = $data->{'data_fields'};
		$this->api = $api;
	}
	
	public function getID(){
		return $this->id;
	}
	
	public function getName(){
		return $this->name;
	}
	
	public function getData_fields(){
		return $this->data_fields;
	}
	
	/**
     * This method disconnects the parent sensor from the service. The service will be stopped if it's not used by other sensors.
     *
     * @access public
     * @param  int sensorID
     * @return mixed
     */
    public function disconnectSensor( $sensorID)
    {
		return $this->api->disconnectFromService($sensorID, $this->getID());
    }
	
	
} /* end of class Service */

?>