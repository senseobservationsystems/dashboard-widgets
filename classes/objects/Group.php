<?php

error_reporting(E_ALL);
/**
 * sense dashboard - Group.php
 *
 * This file is part of sense dashboard.
 *
 * @author Remi Appels <remi@sense-os.nl>
 */

if (0 > version_compare(PHP_VERSION, '5')) {
    die('This file was made for PHP 5');
}

/**
 * The class Group handels all the group actions
 *
 * @access public
 * @author Remi Appels <remi@sense-os.nl>
 */
class Group
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
     * @var string
     */
    private $email = null;
	
	
	
	// --- OPERATIONS ---

	public function Group(stdClass $data, Api $api ){
		$this->id = $data->{'id'};
		$this->name = $data->{'name'};
		if(isset($data->{'email'}))
			$this->email = $data->{'email'};
		$this->api = $api;
	}
	
	public function getID(){
		return $this->id;
	}
	
	public function getName(){
		return $this->name;
	}
	
	public function getEmail(){
		return $this->email;
	}
	
	/**
     * This method will update the details of a group. Only the values specified as input will be updates. Every member of the group can update the group details
     *
     * @access public
     * @param  string email
     * @param  string username
     * @param  string password
     * @param  string name
     * @return json object
     */
	public function update($email, $username, $password, $name){
		return $this->api->updateGroup($this->getID(), $email, $username, $password, $name);
	}
	
	/**
     * This method deletes the group if the group has no other members. If the group has other members then the current user will be removed from the group.
     *
     * @access public
     * @return json object
     */
	public function delete(){
		return $this->api->deleteGroup($this->getID());
	}
	
	
	/**
     * This methods returns the members of the group as a list of users. Only group members can perform this action.
     *
     * @access public
     * @return mixed
     */
    public function getUsers()
    {
		return $this->api->listUsersOfGroup($this->getID());
    }
	
	/**
     * This method will add a user to the group. To add a user at least a username or user_id must be specified. Only members of the group can add a user to the group.
     *
     * @access public
     * @param  int userID
     * @param  string userName
     * @return mixed
     */
    public function addUser($userID, $userName)
    {
		return $this->api->addUserToGroup($this->getID(), $userID, $userName);
    }
	
} /* end of class Group */

?>