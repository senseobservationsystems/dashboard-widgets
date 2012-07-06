<?php

error_reporting(E_ALL);
/**
 * sense dashboard - Device.php
 *
 * This file is part of sense dashboard.
 *
 * @author Remi Appels <remi@sense-os.nl>
 */

if (0 > version_compare(PHP_VERSION, '5')) {
    die('This file was made for PHP 5');
}

/**
 * The class Device handels all the device actions
 *
 * @access public
 * @author Remi Appels <remi@sense-os.nl>
 */
class Device
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
    private $type = null;
	
	/**
     * @access private
     * @var int
     */
    private $uuid = null;
	
	
	
	// --- OPERATIONS ---

	public function Device(stdClass $data, Api $api ){
		$this->id = $data->{'id'};
		$this->type = $data->{'type'};
		$this->uuid = $data->{'uuid'};
		$this->api = $api;
	}
	
	public function getID(){
		return $this->id;
	}
	
	public function getType(){
		return $this->type;
	}
	
	public function getUniqueID(){
		return $this->uuid;
	}
	
	 /**
     * Returns the sensors that are physically connected to the device
     *
     * @access public
	 * @param  int page
	 * @param  int perPage
	 * @param  boolean details
     * @return array of Sensor objects
     */
	public function getMySensors($page, $perPage, $details){
		return $this->api->readDeviceSensors($this->id, $page, $perPage, $details);
	}
} /* end of class Device */

?>