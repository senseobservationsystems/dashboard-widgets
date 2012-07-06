<?php

error_reporting(E_ALL);
/**
 * sense dashboard - User.php
 *
 * This file is part of sense dashboard.
 *
 * @author Remi Appels <remi@sense-os.nl>
 */

if (0 > version_compare(PHP_VERSION, '5')) {
    die('This file was made for PHP 5');
}

/**
 * The class User handels all the user actions
 *
 * @access public
 * @author Remi Appels <remi@sense-os.nl>
 */
class User
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
    private $email = null;
	
	/**
     * @access private
     * @var string
     */
    private $username = null;
	
	/**
     * @access private
     * @var string
     */
    private $name = null;
	
	/**
     * @access private
     * @var string
     */
    private $surname = null;
	
	/**
     * @access private
     * @var string
     */
    private $mobile = null;
	
	/**
     * @access private
     * @var string
     */
    private $UUID = null;
	
	/**
     * @access private
     * @var string
     */
    private $openid = null;
	
	/**
     * @access private
     * @var int
     */
    private $databaseUserId = null;
	
	
	// --- OPERATIONS --- 

	public function User(stdClass $data, Api $api ){
		$this->id = $data->{'id'};
		$this->email = $data->{'email'};
		if(isset($data->{'username'}))
			$this->username = $data->{'username'};
		$this->name = $data->{'name'};
		$this->surname = $data->{'surname'};
		if(isset($data->{'mobile'}))
			$this->mobile = $data->{'mobile'};
		if(isset($data->{'UUID'}))
			$this->UUID = $data->{'UUID'};
		if(isset($data->{'openid'}))
			$this->openid = $data->{'openid'};
		$this->api = $api;
	}
	
	public function getID(){
		return $this->id;
	}
	
	public function getEmail(){
		return $this->email;
	}
	
	public function getName(){
		return $this->name;
	}
	
	public function getSurname(){
		return $this->surname;
	}
	
	public function getMobile(){
		return $this->mobile;
	}
	
	public function getOpenID(){
		return $this->openid;
	}
	
	public function getUniqueID(){
		return $this->UUID;
	}
	
	/**
     * This method updates the details of the user. Only the user_id of the current user can be selected.
     *
     * @access public
     * @param  string email
     * @param  string username
     * @param  string name
     * @param  string surname
     * @param  string mobile
     * @param  string password
     * @return mixed
     */
    public function update($email, $username, $name, $surname, $mobile, $password)
    {
		return $this->api->updateUser($this->getID(), $email, $username, $name, $surname, $mobile, md5($password));
    }
	
	/**
     * This method will remove the user from the database together with his external services.
     *
     * @access public
     * @return mixed
     */
    public function delete()
    {
		return $this->api->deleterUser($this->getID());
    }
	
	public function deleteAllMyData(){
		return $this->api->deleteAllFiles();
	}
	
	/**
     * This method will add a user to the group. To add a user at least a username or user_id must be specified. Only members of the group can add a user to the group.
     *
     * @access public
     * @param  int groupId
     * @return mixed
     */
    public function addToGroup($groupid)
    {
		return $this->api->addUserToGroup($groupid, $this->getID(), $this->getName());
    }
	
	
} /* end of class User */

?>