<?php

  // Require composer autoloader
  require __DIR__ . '/vendor/autoload.php';

  require __DIR__ . '/dotenv-loader.php';

  use Auth0\SDK\Auth0;

  $domain        = getenv('AUTH0_DOMAIN');
  $client_id     = getenv('AUTH0_CLIENT_ID');
  $client_secret = getenv('AUTH0_CLIENT_SECRET');
  $redirect_uri  = getenv('AUTH0_CALLBACK_URL');
  $audience      = getenv('AUTH0_AUDIENCE');
  //$google_env    = getenv("GOOGLE_APPLICATION_CREDENTIALS");

  if($audience == ''){
    $audience = 'https://' . $domain . '/userinfo';
  }

  $auth0 = new Auth0([
    'domain' => $domain,
    'client_id' => $client_id,
    'client_secret' => $client_secret,
    'redirect_uri' => $redirect_uri,
    'audience' => $audience,
    'scope' => 'openid profile',
    'persist_id_token' => true,
    'persist_access_token' => true,
    'persist_refresh_token' => true,
  ]);

  $userInfo = $auth0->getUser();
 
	/**
	* Expands the home directory alias '~' to the full path.
	* @param string $path the path to expand.
	* @return string the expanded path.
	*/

	function expandHomeDirectory($path) {
	$homeDirectory = getenv('HOME');
	echo "home directory: " . $homeDirectory;
	die;
	if (empty($homeDirectory)) {
		$homeDirectory = getenv('HOMEDRIVE') . getenv('HOMEPATH');
	}
	return str_replace('~', realpath($homeDirectory), $path);
	}

  
  function getClient() {
    $client = new Google_Client();
    $client->setApplicationName('Doug Calls Google API');
    $client->setScopes(Google_Service_PeopleService::CONTACTS_READONLY);
    $client->setAuthConfig('google-setup.json');
    $client->setAccessType('offline');

    // Load previously authorized credentials from a file.
    $credentialsPath = expandHomeDirectory('google-setup.json');
    if (file_exists($credentialsPath)) {
        $accessToken = json_decode(file_get_contents($credentialsPath), true);
    } else {
        // Request authorization from the user.
        $authUrl = $client->createAuthUrl();
        printf("Open the following link in your browser:\n%s\n", $authUrl);
        print 'Enter verification code: ';
        $authCode = trim(fgets(STDIN));

        // Exchange authorization code for an access token.
        $accessToken = $client->fetchAccessTokenWithAuthCode($authCode);

        // Store the credentials to disk.
        if (!file_exists(dirname($credentialsPath))) {
            mkdir(dirname($credentialsPath), 0700, true);
        }
        file_put_contents($credentialsPath, json_encode($accessToken));
        printf("Credentials saved to %s\n", $credentialsPath);
    }
    $client->setAccessToken($accessToken);

    // Refresh the token if it's expired.
    if ($client->isAccessTokenExpired()) {
        $client->fetchAccessTokenWithRefreshToken($client->getRefreshToken());
        file_put_contents($credentialsPath, json_encode($client->getAccessToken()));
    }
    return $client;
}

  // if we find good userInfo we are logged in, so let's look at a google api call
  if ($userInfo) {
	// Get the API client and construct the service object.
	$client = getClient();
	$service = new Google_Service_PeopleService($client);

	// Print the names for up to 10 connections.
	$optParams = array(
	  'pageSize' => 10,
	  'personFields' => 'names,emailAddresses',
	);
	$results = $service->people_connections->listPeopleConnections('people/me', $optParams);

	if (count($results->getConnections()) == 0) {
	  print "No connections found.\n";
	} else {
	  print "People:\n";
	  foreach ($results->getConnections() as $person) {
		if (count($person->getNames()) == 0) {
		  print "No names found for this connection\n";
		} else {
		  $names = $person->getNames();
		  $name = $names[0];
		  printf("%s\n", $name->getDisplayName());
		}
	  }
	}
  }
  
?>
<html>
    <head>
        <script src="http://code.jquery.com/jquery-3.1.0.min.js" type="text/javascript"></script>

        <meta name="viewport" content="width=device-width, initial-scale=1">

        <!-- font awesome from BootstrapCDN -->
        <link href="//maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css" rel="stylesheet">
        <link href="//maxcdn.bootstrapcdn.com/font-awesome/4.5.0/css/font-awesome.min.css" rel="stylesheet">

        <link href="public/app.css" rel="stylesheet">



    </head>
    <body class="home">
        <div class="container">
            <div class="login-page clearfix">
              <?php if(!$userInfo): ?>
              <div class="login-box auth0-box before">
                <img src="https://i.cloudup.com/StzWWrY34s.png" />
                <h3>Auth0 Secured</h3>
                <p>Built for developers</p>
                <a class="btn btn-primary btn-lg btn-login btn-block" href="login.php">Sign In</a>
              </div>
              <?php else: ?>
              <div class="logged-in-box auth0-box logged-in">
                <h1 id="logo"><img src="//cdn.auth0.com/samples/auth0_logo_final_blue_RGB.png" /></h1>
                <img class="avatar" src="<?php echo $userInfo['picture'] ?>"/>
                <h2>Welcome <span class="nickname"><?php echo $userInfo['nickname'] ?></span></h2>
                <a class="btn btn-warning btn-logout" href="/logout.php">Logout</a>
              </div>
              <?php endif ?>
            </div>
        </div>
    </body>
</html>
