<?php

namespace SimpleJWTLogin\Modules;

use Exception;
use SimpleJWTLogin\ErrorCodes;
use SimpleJWTLogin\Helpers\Jwt\JwtKeyDecryptionKey;
use SimpleJWTLogin\Helpers\Jwt\JwtKeyFactory;
use SimpleJWTLogin\Libraries\JWT;
use WP_REST_Response;
use WP_User;

class SimpleJWTLoginService {
	const JWT_LEEVAY = 60; //seconds

	/**
	 * @var array
	 */
	private $request;

	/**
	 * @var string
	 */
	private $jwt = '';

	/**
	 * @var SimpleJWTLoginSettings
	 */
	private $jwt_settings;
	/**
	 * @var WordPressData
	 */
	private $wordPressData;

	/**
	 * @var array
	 */
	private $cookie;

	/**
	 * @var array
	 */
	private $session;

	/**
	 * @param SimpleJWTLoginSettings $settings
	 *
	 * @return SimpleJWTLoginService
	 */
	public function withSettings( SimpleJWTLoginSettings $settings ) {
		$this->jwt_settings = $settings;

		$this->wordPressData = $settings->getWordPressData();

		return $this;
	}

	/**
	 * @param array $request
	 *
	 * @return $this
	 */
	public function withRequest( $request ) {
		$this->request = $request;

		return $this;
	}

	public function getRouteNamespace() {
		return $this->jwt_settings->getRouteNamespace();
	}

	/**
	 * @param string $userData
	 *
	 * @return bool|WP_User
	 */
	private function getUserDetails( $userData ) {
		switch($this->jwt_settings->getJWTLoginBy()){
			case SimpleJWTLoginSettings::JWT_LOGIN_BY_EMAIL:
				$user = $this->wordPressData->getUserDetailsByEmail($userData);
				break;
			case SimpleJWTLoginSettings::JWT_LOGIN_BY_USER_LOGIN:
				$user = $this->wordPressData->getUserByUserLogin($userData);
				break;
			case SimpleJWTLoginSettings::JWT_LOGIN_BY_WORDPRESS_USER_ID:
			default:
				$user = $this->wordPressData->getUserDetailsById($userData);
				break;
		}

		if ( $user === false ) {
			return false;
		}

		return $user;
	}

	/**
	 * @throws Exception
	 */
	public function doLogin() {
		$this->validateDoLogin();
		$login_parameter = $this->validateTokenByParameter( $this->jwt_settings->getJwtLoginByParameter() );

		$user = $this->getUserDetails( $login_parameter );
		if ( $user === false ) {
			throw new Exception(
				__( 'User not found.', 'simple-jwt-login' ),
				ErrorCodes::ERR_DO_LOGIN_USER_NOT_FOUND
			);
		}

		$this->wordPressData->loginUser( $user );
		if ( $this->jwt_settings->isHookEnable( SimpleJWTLoginHooks::LOGIN_ACTION_NAME ) ) {
			$this->wordPressData->triggerAction( SimpleJWTLoginHooks::LOGIN_ACTION_NAME, $user );
		}

		$this->redirectAfterLogin();

	}

	/**
	 * @return array
	 */
	function getallheaders() {
		if ( function_exists( 'getallheaders' ) ) {
			return getallheaders();
		}

		$headers = [];
		foreach ( $_SERVER as $name => $value ) {
			if ( substr( $name, 0, 5 ) == 'HTTP_' ) {
				$key             = str_replace(
					' ',
					'-',
					ucwords( strtolower( str_replace( '_', ' ', substr( $name, 5 ) ) ) )
				);
				$headers[ $key ] = $value;
			}
		}

		return $headers;
	}

	/**
	 * @return string|null
	 */
	public function getJwtFromRequestHeaderOrCookie() {

		if ( $this->jwt_settings->getJwtFromHeaderEnabled() ) {
			$headers = array_change_key_case( $this->getallheaders(), CASE_LOWER );
			if ( isset( $headers['authorization'] ) ) {
				preg_match(
					'/(?:Bearer[ ]?)?(.*)/m',
					$headers['authorization'],
					$matches
				);
				if ( isset( $matches[1] ) ) {
					return $matches[1];
				}
			}
		}
		if ( $this->jwt_settings->getJwtFromCookieEnabled() ) {

			if ( isset( $this->cookie['simple-jwt-login-token'] ) ) {
				return $this->cookie['simple-jwt-login-token'];
			}
		}

		if ( $this->jwt_settings->getJwtFromSessionEnabled() ) {
			if ( isset( $this->session['simple-jwt-login-token'] ) ) {
				return $this->session['simple-jwt-login-token'];
			}
		}

		$request = array_change_key_case( $this->request, CASE_LOWER );

		return $this->jwt_settings->getJwtFromURLEnabled() && isset( $request['jwt'] )
			? $request['jwt']
			: null;
	}

	/**
	 * @throws Exception
	 */
	private function validateDoLogin() {
		$this->jwt = $this->getJwtFromRequestHeaderOrCookie();
		if ( $this->jwt_settings->getAllowAutologin() === false ) {
			throw new Exception(
				__( 'Auto-login is not enabled on this website.', 'simple-jwt-login' ),
				ErrorCodes::ERR_AUTO_LOGIN_NOT_ENABLED
			);
		}

		if ( empty( $this->jwt ) ) {
			throw new Exception( __( 'Wrong Request.', 'simple-jwt-login' ),
				ErrorCodes::ERR_VALIDATE_LOGIN_WRONG_REQUEST );
		}

		if ( $this->jwt_settings->getRequireLoginAuthKey() && $this->validateAuthKey() === false ) {
			throw  new Exception(
				sprintf(
					__( 'Invalid Auth Code ( %s ) provided.', 'simple-jwt-login' ),
					$this->jwt_settings->getAuthCodeKey()
				),
				ErrorCodes::ERR_INVALID_AUTH_CODE_PROVIDED
			);
		}
		if ( ! empty( $this->jwt_settings->getAllowedLoginIps() ) ) {
			$client_ip = $this->getClientIP();
			if ( ! in_array(
				$client_ip,
				array_map( 'trim',
					explode( ',', $this->jwt_settings->getAllowedLoginIps()
					)
				) )
			) {
				throw new Exception(
					sprintf(
						__( 'This IP[ %s] is not allowed to auto-login.', 'simple-jwt-login' ),
						$client_ip
					),
					ErrorCodes::ERR_IP_IS_NOT_ALLOWED_TO_LOGIN
				);
			}
		}
	}

	/**
	 * Do the actual redirect after login
	 */
	private function redirectAfterLogin() {
		$redirect = $this->jwt_settings->getRedirect();

		switch ( $redirect ) {
			case SimpleJWTLoginSettings::REDIRECT_HOMEPAGE:
				$url = $this->wordPressData->getSiteUrl();
				break;
			case SimpleJWTLoginSettings::REDIRECT_CUSTOM:
				$url = $this->jwt_settings->getCustomRedirectURL();
				break;
			case SimpleJWTLoginSettings::REDIRECT_DASHBOARD:
			default:
				$url = $this->wordPressData->getAdminUrl();
				break;
		}

		if ( $this->jwt_settings->getShouldIncludeRequestParameters() ) {
			$requestParams = $this->request;
			$dangerousKeys = [
				'rest_route',
				'jwt',
				'JWT',
				'email',
				'password',
				$this->jwt_settings->getAuthCodeKey()
			];
			foreach ( $dangerousKeys as $key ) {
				if ( isset( $requestParams[ $key ] ) ) {
					unset( $requestParams[ $key ] );
				}
			}

			$url = $url . ( strpos( '?', $url ) !== false ? '&' : '?' ) . http_build_query( $requestParams );
		}

		if ( $this->jwt_settings->isHookEnable( SimpleJWTLoginHooks::LOGIN_REDIRECT_NAME ) ) {
			$this->wordPressData->triggerAction( SimpleJWTLoginHooks::LOGIN_REDIRECT_NAME, $url, $this->request );
		}

		$this->wordPressData->redirect( $url );
	}


	/**
	 * @param $parameter
	 *
	 * @return mixed|string
	 * @throws Exception
	 */
	private function validateTokenByParameter( $parameter ) {
		JWT::$leeway = self::JWT_LEEVAY;
		$decoded     = (array) JWT::decode(
			$this->jwt,
			JwtKeyFactory::getFactory($this->jwt_settings)->getPublicKey(),
			[ $this->jwt_settings->getJWTDecryptAlgorithm() ]
		);

		if ( strpos( $parameter, '.' ) !== false ) {
			$array = explode( '.', $parameter );
			foreach ( $array as $value ) {
				$decoded = (array) $decoded;
				if ( isset( $decoded[ $value ] ) ) {
					$decoded = $decoded[ $value ];
				} else {
					throw new Exception(
						sprintf(
							__( 'Unable to find user %s property in JWT.( Settings: %s )', 'simple-jwt-login' ),
							$value, $parameter
						),
						ErrorCodes::ERR_UNABLE_TO_FIND_PROPERTY_FOR_USER_IN_JWT
					);
				}
			}

			return (string) $decoded;
		}

		if ( ! isset( $decoded[ $parameter ] ) ) {
			throw new Exception( sprintf(
				__( 'Unable to find user %s property in JWT.', 'simple-jwt-login' ),
				$parameter
			),
				ErrorCodes::ERR_JWT_PARAMETER_FOR_USER_NOT_FOUND
			);
		}

		return $decoded[ $parameter ];
	}

	/**
	 * @return bool
	 */
	private function validateAuthKey() {
		$authCodeKey = $this->jwt_settings->getAuthCodeKey();
		if ( ! isset( $this->request[ $authCodeKey ] ) ) {
			return false;
		}
		foreach ( $this->jwt_settings->getAuthCodes() as $code ) {
			$authCodeBuilder = new AuthCodeBuilder($code);
			if(!empty($authCodeBuilder->getExpirationDate()) && (strtotime($authCodeBuilder->getExpirationDate()) < time()) ){
				return false;
			}
			if ( $authCodeBuilder->getCode() === $this->request[ $authCodeKey ] ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * @throws Exception
	 */
	public function validateRegisterUser() {
		if ( $this->jwt_settings->getAllowRegister() === false ) {
			throw  new Exception(
				__( 'Register is not allowed.', 'simple-jwt-login' ),
				ErrorCodes::ERR_REGISTER_IS_NOT_ALLOWED
			);
		}

		if (
		(
			$this->jwt_settings->getRequireRegisterAuthKey()
			|| isset($this->request[$this->jwt_settings->getAuthCodeKey()])
		) && $this->validateAuthKey() === false
		) {
			throw  new Exception(
				sprintf(
					__( 'Invalid Auth Code ( %s ) provided.', 'simple-jwt-login' ),
					$this->jwt_settings->getAuthCodeKey()
				),
				ErrorCodes::ERR_REGISTER_INVALID_AUTH_KEY
			);
		}

		if ( ! empty( $this->jwt_settings->getAllowedRegisterIps() ) ) {
			$client_ip = $this->getClientIP();
			if ( ! in_array( $client_ip, array_map( 'trim',
					explode( ',', $this->jwt_settings->getAllowedRegisterIps() ) )
			)
			) {
				throw new Exception(
					sprintf(
						__( 'This IP[%s] is not allowed to register users.', 'simple-jwt-login' ),
						$client_ip
					),
					ErrorCodes::ERR_REGISTER_IP_IS_NOT_ALLOWED
				);
			}
		}

		if ( ! isset( $this->request['email'] ) || ! isset( $this->request['password'] ) && $this->jwt_settings->getRandomPasswordForCreateUser() === false ) {
			throw new Exception(
				__( 'Missing email or password.', 'simple-jwt-login' ),
				ErrorCodes::ERR_REGISTER_MISSING_EMAIL_OR_PASSWORD
			);
		}

		if ( $this->wordPressData->is_email( $this->request['email'] ) === false ) {
			throw  new Exception(
				__( 'Invalid email address.', 'simple-jwt-login' ),
				ErrorCodes::ERR_REGISTER_INVALID_EMAIL_ADDRESS
			);
		}

		if ( ! empty( $this->jwt_settings->getAllowedRegisterDomain() ) ) {
			$parts = explode( '@', $this->request['email'] );
			if ( ! isset( $parts[1] ) || isset( $parts[1] ) && ! in_array( $parts[1], array_map( 'trim',
					explode( ',', $this->jwt_settings->getAllowedRegisterDomain() )
				) )
			) {
				throw new Exception(
					__( 'This website does not allows users from this domain.', 'simple-jwt-login' ),
					ErrorCodes::ERR_REGISTER_DOMAIN_FOR_USER
				);
			}
		}
	}

	/**
	 * @return WP_REST_Response
	 * @throws Exception
	 */
	public function createUser() {
		$email = $this->request['email'];
		$extraParameters = UserProperties::getExtraParametersFromRequest( $this->request );
		$username = !empty($extraParameters['user_login'])
			? $extraParameters['user_login']
			: $email;

		if ( $this->wordPressData->checkUserExistsByUsernameAndEmail( $username, $email ) == true ) {
			throw new Exception(
				__( 'User already exists.', 'simple-jwt-login' ),
				ErrorCodes::ERR_REGISTER_USER_ALREADY_EXISTS
			);
		}

		$password = $this->jwt_settings->getRandomPasswordForCreateUser()
			? $this->randomString( 10 )
			: $this->request['password'];

		$newUserRole = $this->jwt_settings->getNewUSerProfile();
		if(isset($this->request[$this->jwt_settings->getAuthCodeKey()])){
			$authCodes = $this->jwt_settings->getAuthCodes();
			foreach ($authCodes as $code){
				$authCodeBuilder = new AuthCodeBuilder($code);
				if(
					$authCodeBuilder->getCode() === $this->request[$this->jwt_settings->getAuthCodeKey()]
				   && $authCodeBuilder->getRole() !== null
				){
					$newUserRole = $authCodeBuilder->getRole();
				}
			}
		}

		$user = $this->wordPressData->createUser(
			$username,
			$email,
			$password,
			$newUserRole,
			$extraParameters
		);
		$userId = $this->wordPressData->getUserIdFromUser($user);

		if ( $this->jwt_settings->isHookEnable( SimpleJWTLoginHooks::REGISTER_ACTION_NAME ) ) {
			$this->wordPressData->triggerAction( SimpleJWTLoginHooks::REGISTER_ACTION_NAME, $userId, $password );
		}

		if ( $this->jwt_settings->getAllowAutologin() && $this->jwt_settings->getForceLoginAfterCreateUser() ) {
			$this->wordPressData->loginUser( $user );
			if ( $this->jwt_settings->isHookEnable( SimpleJWTLoginHooks::LOGIN_ACTION_NAME ) ) {
				$this->wordPressData->triggerAction( SimpleJWTLoginHooks::LOGIN_ACTION_NAME, $userId );
			}
			$this->redirectAfterLogin();
		}

		$userArray = $this->wordPressData->wordpressUserToArray($user);
		if(isset($userArray['user_pass'])) {
			unset( $userArray['user_pass'] );
		}
		return $this->wordPressData->createResponse( [
			'success' => true,
			'id'      => $userId,
			'message' => __( 'User was successfully created.', 'simple-jwt-login' ),
			'user'    => $userArray
		]);

	}

	/**
	 * @return string|null
	 */
	private function getClientIP() {
		$ip = null;
		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) )   //check ip from share internet
		{
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) )   //to check ip is pass from proxy
		{
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = $_SERVER['REMOTE_ADDR'];
		}

		return $ip;
	}

	/**
	 * @param int $length
	 *
	 * @return false|string
	 */
	private function randomString( $length = 8 ) {

		$chars = "abcdefghjkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ23456789";

		return substr( str_shuffle( $chars ), 0, $length );

	}

	/**
	 * Main Function for Delete user route
	 * @throws Exception
	 */
	public function deleteUser() {
		$this->jwt = $this->getJwtFromRequestHeaderOrCookie();
		if ( empty( $this->jwt ) ) {
			throw new \Exception( __( 'The `jwt` parameter is missing.', 'simple-jwt-login' ),
				ErrorCodes::ERR_DELETE_MISSING_JWT );
		}

		if ( $this->jwt_settings->getAllowDelete() === false ) {
			throw  new Exception( __( 'Delete is not enabled.', 'simple-jwt-login' ),
				ErrorCodes::ERR_DELETE_IS_NOT_ENABLED );
		}
		if ( $this->jwt_settings->getRequireDeleteAuthKey() && ! isset( $this->request[ $this->jwt_settings->getAuthCodeKey() ] ) ) {
			throw new Exception(
				sprintf( __( 'Missing AUTH KEY ( %s ).', 'simple-jwt-login' ), $this->jwt_settings->getAuthCodeKey() ),
				ErrorCodes::ERR_DELETE_MISSING_AUTH_KEY
			);
		}

		$allowedIpsString = trim( $this->jwt_settings->getAllowedDeleteIps() );
		if ( ! empty( $allowedIpsString ) ) {
			$allowedIps = explode( ',', $allowedIpsString );
			$clientIp   = $this->getClientIP();
			if ( ! empty( $allowedIps ) && ! in_array( $clientIp, $allowedIps ) ) {
				throw new \Exception(
					sprintf( __( 'You are not allowed to delete users from this IP: %s', 'simple-jwt-login' ),
						$clientIp ),
					ErrorCodes::ERR_DELETE_INVALID_CLIENT_IP
				);
			}
		}


		$registerParameter = $this->validateTokenByParameter( $this->jwt_settings->getJwtDeleteByParameter() );
		$user              = $this->jwt_settings->getDeleteUserBy() === SimpleJWTLoginSettings::DELETE_USER_BY_ID
			? $this->wordPressData->getUserDetailsById( $registerParameter )
			: $this->wordPressData->getUserDetailsByEmail( $registerParameter );

		if ( $user === false ) {
			throw new Exception(
				__( 'User not found.', 'simple-jwt-login' ),
				ErrorCodes::ERR_DO_LOGIN_USER_NOT_FOUND
			);
		}

		$result = $this->wordPressData->deleteUser( $user );

		if ( $result === false ) {
			throw new Exception(
				__( 'User not found.', 'simple-jwt-login' ),
				ErrorCodes::ERR_DO_LOGIN_USER_NOT_FOUND
			);
		}

		if ( $this->jwt_settings->isHookEnable( SimpleJWTLoginHooks::DELETE_USER_ACTION_NAME ) ) {
			$this->wordPressData->triggerAction( SimpleJWTLoginHooks::DELETE_USER_ACTION_NAME, $user );
		}

		return $this->wordPressData->createResponse( [
			'message' => __( 'User was successfully deleted.', 'simple-jwt-login' ),
			'id'      => $result
		] );
	}

	/**
	 * @param array $cookie
	 *
	 * @return $this
	 */
	public function withCookie( $cookie ) {
		$this->cookie = $cookie;

		return $this;
	}

	/**
	 * @param array $session
	 *
	 * @return $this
	 */
	public function withSession( $session ) {
		$this->session = $session;

		return $this;
	}

	/**
	 * @return WP_REST_Response
	 * @throws Exception
	 */
	public function authenticateUser() {
		//Validate authentication
		if ( $this->jwt_settings->isAuthenticationEnabled() === false ) {
			throw new Exception(
				__( 'Authentication is not enabled.', 'simple-jwt-login' ),
				ErrorCodes::AUTHENTICATION_IS_NOT_ENABLED
			);
		}
		if ( ! isset( $this->request['email'] ) && !isset($this->request['username']) ) {
			throw new Exception(
				__('The email or username parameter is missing from request.','simple-jwt-login'),
				ErrorCodes::AUTHENTICATION_MISSING_EMAIL
			);
		}
		if ( ! isset( $this->request['password'] ) ) {
			throw new Exception(
				__('The password parameter is missing from request.','simple-jwt-login'),
				ErrorCodes::AUTHENTICATION_MISSING_PASSWORD
			);
		}
		$user = isset($this->request['username'])
			? $this->wordPressData->getUserByUserLogin($this->request['username'])
			: $this->wordPressData->getUserDetailsByEmail( $this->request['email'] );

		if ( empty( $user ) ) {
			throw new Exception(
				__('Wrong user credentials.', 'simple-jwt-login'),
				ErrorCodes::AUTHENTICATION_WRONG_CREDENTIALS
			);
		}
		$password   = $this->request['password'];
		$dbPassword = $user->get( 'user_pass' );

		$passwordMatch = wp_check_password( $password, $dbPassword );
		if ( $passwordMatch === false ) {
			throw new Exception(
				__('Wrong user credentials.', 'simple-jwt-login'),
				ErrorCodes::AUTHENTICATION_WRONG_CREDENTIALS
			);
		}

		//Generate payload
		$payload = [
			SimpleJWTLoginSettings::JWT_PAYLOAD_PARAM_IAT => time(),
		];
		foreach ( $this->jwt_settings->getJwtPayloadParameters() as $parameter ) {
			if (
				$parameter === SimpleJWTLoginSettings::JWT_PAYLOAD_PARAM_IAT
				|| $this->jwt_settings->isPayloadDataEnabled( $parameter ) === false
			) {
				continue;
			}

			switch ( $parameter ) {
				case SimpleJWTLoginSettings::JWT_PAYLOAD_PARAM_EXP:
					$payload[ $parameter ] = time() + ( (int) $this->jwt_settings->getAuthJwtTtl() * 60 );
					break;
				case SimpleJWTLoginSettings::JWT_PAYLOAD_PARAM_ID:
					$payload[ $parameter ] = $user->get( 'id' );
					break;
				case SimpleJWTLoginSettings::JWT_PAYLOAD_PARAM_EMAIL:
					$payload[ $parameter ] = $user->get( 'user_email' );
					break;
				case SimpleJWTLoginSettings::JWT_PAYLOAD_PARAM_SITE:
					$payload[ $parameter ] = $this->wordPressData->getSiteUrl();
					break;
				case SimpleJWTLoginSettings::JWT_PAYLOAD_PARAM_USERNAME:
					$payload[$parameter] = $user->get('user_login');
					break;
			}
		}

		//Display result
		return $this->wordPressData->createResponse( [
			'success' => true,
			'data'    => [
				'jwt' => JWT::encode(
					$payload,
					JwtKeyFactory::getFactory($this->jwt_settings)->getPrivateKey(),
					$this->jwt_settings->getJWTDecryptAlgorithm()
				)
			]
		] );
	}

	/**
	 * @return WP_REST_Response
	 * @throws Exception
	 */
	public function refreshJwt() {
		//Validate authentication
		if ( $this->jwt_settings->isAuthenticationEnabled() === false ) {
			throw new Exception(
				__( 'Authentication is not enabled.', 'simple-jwt-login' ),
				ErrorCodes::AUTHENTICATION_IS_NOT_ENABLED
			);
		}

		$this->jwt = $this->getJwtFromRequestHeaderOrCookie();
		if ( empty( $this->jwt ) ) {
			throw new Exception(
				__('JWT is missing.','simple-jwt-login'),
				ErrorCodes::ERR_JWT_NOT_FOUND_ON_AUTH_REFRESH
			);
		}

		try {
			JWT::$leeway = self::JWT_LEEVAY;
			JWT::decode(
				$this->jwt,
				JwtKeyFactory::getFactory($this->jwt_settings)->getPublicKey(),
				[ $this->jwt_settings->getJWTDecryptAlgorithm() ]
			);
		} catch ( \Exception $e ) {
			if ( $e->getCode() !== ErrorCodes::ERR_TOKEN_EXPIRED ) {
				throw new Exception( $e->getMessage(), $e->getCode() );
			}
		}

		list( $header, $payload, $signature ) = explode( ".", $this->jwt );
		$payload = json_decode( base64_decode( $payload ), true );

		if ( $payload === null ) {
			throw new Exception(
				__('There was an error with your JWT and we can not refresh it.', 'simple-jwt-login'),
				ErrorCodes::ERR_JWT_REFRESH_NULL_PAYLOAD
			);
		}

		if ( isset( $payload[ SimpleJWTLoginSettings::JWT_PAYLOAD_PARAM_EXP ] ) ) {
			$refreshTimeToLive =
				$payload[ SimpleJWTLoginSettings::JWT_PAYLOAD_PARAM_EXP ]
				+ $this->jwt_settings->getAuthJwtRefreshTtl() * 60;

			if ( time() > $refreshTimeToLive ) {
				throw new Exception(
					__('JWT is too old to be refreshed.', 'simple-jwt-login'),
					ErrorCodes::ERR_JWT_REFRESH_JWT_TOO_OLD
				);
			}

			$payload[ SimpleJWTLoginSettings::JWT_PAYLOAD_PARAM_EXP ] = time() + ( $this->jwt_settings->getAuthJwtTtl() * 60 );
		}

		//Display result
		return $this->wordPressData->createResponse( [
			'success' => true,
			'data'    => [
				'jwt' => JWT::encode(
					$payload,
					JwtKeyFactory::getFactory($this->jwt_settings)->getPrivateKey(),
					$this->jwt_settings->getJWTDecryptAlgorithm()
				)
			]
		] );

	}

	/**
	 * @return WP_REST_Response
	 * @throws Exception
	 */
	public function validateAuth() {

		$this->jwt = $this->getJwtFromRequestHeaderOrCookie();
		if ( empty( $this->jwt ) ) {
			throw new \Exception( __( 'The `jwt` parameter is missing.', 'simple-jwt-login' ),
				ErrorCodes::ERR_MISSING_JWT_AUTH_VALIDATE );
		}

		$login_parameter = $this->validateTokenByParameter( $this->jwt_settings->getJwtLoginByParameter() );

		$user = $this->getUserDetails( $login_parameter );
		if ( $user === false ) {
			throw new Exception(
				__( 'User not found.', 'simple-jwt-login' ),
				ErrorCodes::ERR_DO_LOGIN_USER_NOT_FOUND
			);
		}
		$userArray  = $user->to_array();
		if(isset($userArray['user_pass'])) {
			unset( $userArray['user_pass'] );
		}
		$jwtParameters = [];
		$jwtParameters['token'] = $this->jwt;
		list($header, $payload ) = explode('.', $this->jwt);
		$jwtParameters['header'] = @json_decode(base64_decode($header), true);
		$jwtParameters['payload'] = @json_decode(base64_decode($payload), true);
		if(isset($jwtParameters['payload']['exp'])){
			$jwtParameters['expire_in'] = $jwtParameters['payload']['exp'] - time();
		}

		return $this->wordPressData->createResponse( [
			'success' => true,
			'data'    => [
				'user' => $userArray,
				'jwt' => [
					$jwtParameters
				]
			]
		] );
	}

	/**
	 * @param string $jwt
	 *
	 * @return bool|int
	 * @throws Exception
	 */
	public function getUserIdFromJWT($jwt){
		$this->jwt = $jwt;
		$login_parameter = $this->validateTokenByParameter( $this->jwt_settings->getJwtLoginByParameter() );
		$user            = $this->getUserDetails( $login_parameter );
		if($user === false){
			throw new Exception(
				__('WordPress User not found.','simple-jwt-login'),
				ErrorCodes::ERR_GET_USER_ID_FROM_JWT
			);
		}
		return (int) $user->get('id');
	}
}
