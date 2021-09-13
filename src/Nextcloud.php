<?php

namespace Fusio\Impl\Provider\User;

use Fusio\Engine\Model\User;
use Fusio\Engine\User\ProviderInterface;
use Fusio\Impl\Base;
use Fusio\Impl\Service\Config;
use PSX\Http\Client\ClientInterface;
use PSX\Http\Client\GetRequest;
use PSX\Http\Client\PostRequest;
use PSX\Json\Parser;
use PSX\Uri\Url;
use RuntimeException;


class Nextcloud implements ProviderInterface
{
	
   const PROVIDER_NEXTCLOUD   = 0x6;
   const PROVIDER_SUFFIX = '.frdl';

    protected $httpClient;

 
    protected $secret;
	
 	 
    protected $scope = [];

 
    protected $apiBaseUrl = 'https://webfan.de';

  
    protected $authorizeUrl = 'https://webfan.de/index.php/apps/oauth2/authorize';

 
    protected $accessTokenUrl = 'https://webfan.de/index.php/apps/oauth2/api/v1/token';
     
    protected $profileUrl = 'https://webfan.de/ocs/v2.php/cloud/user?format=json';
	

	
	
    public function __construct(ClientInterface $httpClient, Config $config)
    {
			
        $this->httpClient = $httpClient;
        $this->secret     = $config->getValue('provider_nextcloud_secret');
		
		$this->apiBaseUrl = $config->getValue('provider_nextcloud_url');
		$this->authorizeUrl =$this->apiBaseUrl.'/index.php/apps/oauth2/authorize';
		$this->accessTokenUrl = $this->apiBaseUrl.'/index.php/apps/oauth2/api/v1/token';
		$this->profileUrl = $this->apiBaseUrl.'/ocs/v2.php/cloud/user?format=json';
	    $this->scope=[];
    }

 
    public function getId()
    {
        return self::PROVIDER_NEXTCLOUD;
    }

 
    public function requestUser($code, $clientId, $redirectUri)
    {
		
		
		try{
        $accessToken = $this->getAccessToken($code, $clientId, $this->secret, $redirectUri);
		}catch(\Exception $e){
		 //  return ['error'=>$e->getMessage()];	
			  throw new \Exception($e->getMessage());
			return null;
		}
		
        if (!empty($accessToken)) {
            $url      = new Url($this->profileUrl);
            $headers  = [
                'Authorization' => 'Bearer ' . $accessToken,
                'User-Agent'    => Base::getUserAgent()
            ];

			
			
            $response = $this->httpClient->request(new GetRequest($url, $headers));
	
            if ($response->getStatusCode() == 200) {
                $data  = Parser::decode($response->getBody());
				
				
                $id    = isset($data->ocs->data->id) ? $data->ocs->data->id : null;
                $name  = isset($data->ocs->data->{'display-name'}) ? $data->ocs->data->{'display-name'} : null;
                $email = isset($data->ocs->data->email) ? $data->ocs->data->email : null;

                if (!empty($id) && !empty($name) && !empty($email)) {
                    $user = new User();
                    $user->setId($id);
                    $user->setName($name.self::PROVIDER_SUFFIX);
                    $user->setEmail($email);

                    return $user;
                }
            }
        }

        return null;
    }

    public function getAccessToken($code, $clientId, $clientSecret, $redirectUri)
    {
	
        if (empty($clientSecret)) {
            throw new RuntimeException('No secret provided');
        }

        $url = new Url($this->accessTokenUrl);

		
        $params = [
            'code'          => $code,
            'client_id'     => $clientId,
            'client_secret' => $clientSecret,
            'redirect_uri'  => $redirectUri,
            'grant_type'    => 'authorization_code',
			      'scope' => implode(',', $this->scope),
        ];

        $headers = [
            'Accept'     => 'application/json',
            'User-Agent' => Base::getUserAgent()
        ];

	
		try{      	
			$response = $this->httpClient->request(new PostRequest($url, $headers, $params));			
		}catch(\Exception $e){
		   throw new \Exception($e->getMessage());
		}

        if ($response->getStatusCode() == 200) {
			try{   
              $data = Parser::decode($response->getBody());
			}catch(\Exception $e){
		           throw new \Exception($e->getMessage());
	    	}
            if (isset($data->access_token)) {
                return $data->access_token;
            }
        }

      
		return null;
    }
}
