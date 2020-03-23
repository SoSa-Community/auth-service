<?php
namespace controllers;

use models\User;
use Ubiquity\exceptions\DAOException;
use Ubiquity\orm\DAO;

/**
 * Login Controller
 **/
class Login extends ControllerBase{
	
	public function index(){return [];}
	
	/**
	 * @post("login")
	 */
	public function login(){
		$response = ['status' => 'failure', 'error' => 'invalid credentials'];
		
		$username = $_POST['username'] ?? null;
		$password = $_POST['password'] ?? null;
		
		if($username && $password){
			try{
				$user = DAO::getOne(User::class, 'username = ?', false, [$_POST['username']]);
				if(!empty($user)){
					
					if($user->verifyPassword($_POST['password'])){
						unset($response['error']);
						
						$response['user'] = $user->getPublicOutput();
						$response['status'] = 'success';
					}
				}
			}catch (DAOException $exception){
			
			}
		}
		echo json_encode($response);
	}
}
