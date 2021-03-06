<?php
namespace controllers\providers;

use controllers\ControllerBase;
use models\Device;
use models\Preauth;
use models\ProviderUser;

use models\User;
use providers\EmailProvider;
use Ubiquity\controllers\Startup;
use Ubiquity\orm\DAO;



/**
 * ControllerBase.
 **/
abstract class PreauthControllerBase extends ControllerBase {
	
	protected $provider = '';
	
	public function setup(){
		
		if(isset($_GET['app'])){
			$_SESSION['app'] = boolval($_GET['app']);
		}else{
			$_SESSION['app'] = false;
		}
		
		if(!empty($_GET['preauth'])){
			$preauth = DAO::getOne(Preauth::class, 'id = ?', false, [$_GET['preauth']]);
			if(!empty($preauth)){
				$_SESSION['preAuth'] = $preauth;
				return true;
			}else{
				throw new \APIError('Invalid Pre-auth provided');
			}
		}else{
			throw new \APIError('Invalid Pre-auth provided');
		}
	}
	
	public function completePreauth($accessToken = '', $accessTokenSecret = '', $refreshToken = '', $expiresIn, $userId, $username, $email=null){
		
		if((isset($_SESSION['preAuth']) && !empty($_SESSION['preAuth']))) {
			$providerUser = DAO::getOne(ProviderUser::class, 'provider = ? AND unique_id = ?', false, [$this->provider, $userId]);
			$user = null;
			
			$expiresIn = intval($expiresIn);
			if(empty($expiresIn))   $expiresIn = 3600;
			
			if (!empty($providerUser) && !empty($providerUser->getUserId())) {
				$user = DAO::getById(User::class, $providerUser->getUserId());
				if(empty($user)){
					DAO::remove($providerUser);
					$providerUser = null;
				}
			}
			
			if(empty($providerUser)) {
				$providerUser = new ProviderUser();
				$providerUser->setProvider($this->provider);
			}
			
			$providerUser->setUniqueId($userId);
			$providerUser->setAccessToken($accessToken);
			$providerUser->setAccessTokenSecret($accessTokenSecret);
			$providerUser->setRefreshToken($refreshToken);
			$providerUser->setAccessTokenExpiry(date('Y-m-j H:i', time() + intval($expiresIn)));
			
			$preAuth = $_SESSION['preAuth'];
			
			if($_SESSION['for_login'] && (!isset($user) || empty($user))) {
				$providerUser->setUserName($username);
				$providerUser->setEmail($email);
				$providerUser->generateAndSetLinkToken();
				$providerUser->setPreauthId($preAuth->getId());
			}
			
			if($user === null && !$_SESSION['for_login']) $user = $this->createPreauthUser($username, $email);
			if(!empty($user)){
				$providerUser->setUserId($user->getId());
				$device = Device::registerDevice($user->getId(), $preAuth->getDeviceSecret(), $preAuth->getDeviceName(), $preAuth->getDevicePlatform());
			}
			
			if (DAO::save($providerUser)) {
				$return = [];
				if(isset($device)){
					$return['device_id'] = $device->getId();
				}else{
					$return['unregistered'] = true;
					$return['link_token'] = $providerUser->getLinkToken();
				}
				return $return;
			} else {
				throw new \Exception('Error saving provider user');
			}
			
		}else{
			throw new \Exception('Invalid Request');
		}
	}
	
	public static function createPreauthUser($username=null, $email=null){
		$user = new User();
		$emailHash = null;
		
		try{
			User::isUsernameValid($username);
		}
		catch (\Exception $e){
			$username = null;
		}
		
		if(!empty($email)){
			try{
				if(User::isEmailValid($email)){
					$emailHash = User::generateEmailHash($email);
				}
			}catch (\Exception $e){
			
			}
		}
		
		if($username !== null || $emailHash !== null){
			$userExistErrors = User::checkUsersExist($username, $emailHash);
			if(!empty($userExistErrors)){
				if(isset($userExistErrors['username'])) $username = null;
				if(isset($userExistErrors['email'])) $emailHash = null;
			}
		}
		
		$user->setUsername($username);
		$user->setEmailHash($emailHash);
		
		if(!empty($email)){
			$emailBody = EmailProvider::renderTemplate('registration', ['username' => $username]);
			EmailProvider::send($email, $username, 'Welcome To SoSa', $emailBody);
		}
		
		if (!DAO::save($user)) throw new \Exception('Failed to save user account');
		return $user;
	}
	
	public function handlePreauthResponse($status='failure', $returnData = []){
		$returnData['status'] = $status;
		$route = $_SESSION['for_login'] ? 'login' : 'register';
		
		if($_SESSION['app']){
			header('Location: sosa://'.$route.'/preauth/'.base64_encode(json_encode($returnData)).'/');
		}else{
			header('Location: '.Startup::$config['websiteURI'].'/preauth/'.$route.'/'.$status.'/'.base64_encode(json_encode($returnData)).'/');
		}
	}
	
	public function getRequestDefaults($postData, $isPost){
		$curlOptions = [];
		
		$curlOptions[CURLOPT_CONNECTTIMEOUT] = 60;
		$curlOptions[CURLOPT_DNS_CACHE_TIMEOUT] = 25;
		$curlOptions[CURLOPT_TIMEOUT] = 60;
		
		$curlOptions[CURLOPT_RETURNTRANSFER] = 1;
		$curlOptions[CURLOPT_FOLLOWLOCATION] = 1;
		$curlOptions[CURLOPT_USERAGENT] = $_SERVER['HTTP_USER_AGENT'];
		
		$curlOptions[CURLOPT_HEADER] = 0;
		$curlOptions[CURLINFO_HEADER_OUT] = 0;
		
		if (!empty($postData) && $isPost){
			$curlOptions[CURLOPT_CUSTOMREQUEST] = 'POST';
			$curlOptions[CURLOPT_POSTFIELDS] = $postData;
		}
		
		return $curlOptions;
	}
	
	

}

