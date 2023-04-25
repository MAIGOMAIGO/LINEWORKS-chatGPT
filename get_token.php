<?php
$client_id='<Client ID>'; // Client ID
$client_secret='<Client secret ID>'; // Client secret ID
$service_account='<Service Account ID>'; // Service Account ID
$private_key_path='<Private Key Path>'; // Private Key Path

// crate JWT Header
$JWTHeader = '{"alg":"RS256","typ":"JWT"}';
$JWTHeaderEnc = base64_encode($JWTHeader);

// create JWT JSON Claim set
$iss = $client_id; // Client ID
$sub = $service_account; // Service Account ID
$iat = time(); // JWT Generation Timestamp: UNIX timestamp (in seconds)
$exp = time() + 3600; // JWT Expiration Timestamp: UNIX timestamp (in seconds) for JWT expiration
$JWTJsonClaim = json_encode(array(
    'iss' => $iss,
    'sub' => $sub,
    'iat' => $iat,
    'exp' => $exp
));
$JWTJsonClaimEnc = base64_encode($JWTJsonClaim);

// create JWT {header BASE64 Encode}.{JSON Claim set BASE64 Encode}
$JWThjcEnc = "{$JWTHeaderEnc}.{$JWTJsonClaimEnc}";

// read private_key file
$private_key = file_get_contents($private_key_path);

// create JWT signature
$JWTsignature = null;
// string data, string signature, string private_key, string algorithm
openssl_sign($JWThjcEnc,$JWTsignature,$private_key,OPENSSL_ALGO_SHA256);
$JWTsignatureEnc = base64_encode($JWTsignature);

// create JWT
$JWT = "{$JWThjcEnc}.{$JWTsignatureEnc}";

// get  AccessToken
// Initialize curl session
$curl = curl_init();
// Set curl options
curl_setopt($curl, CURLOPT_URL, 'https://auth.worksmobile.com/oauth2/v2.0/token'); //set url
curl_setopt($curl, CURLOPT_POST, true); //http post
curl_setopt($curl, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']); // set header
// body
$assertion=$JWT;
$grant_type='urn:ietf:params:oauth:grant-type:jwt-bearer';
$scope='bot,user.read';
$body= http_build_query(array(
  'assertion' => $assertion,
  'grant_type' => $grant_type,
  'client_id' => $client_id,
  'client_secret' => $client_secret,
  'scope' => $scope
));
curl_setopt($curl, CURLOPT_POSTFIELDS, $body); // set request body
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true); // Change to return response as string

// request AccessToken
$response = curl_exec($curl);

// Check if the response is in JSON format
$res = json_decode($response,true);
if(!$res){
    // not Json
    exit;
}
// If the response is in JSON format, use the JSON data

// add created time & expire time
$res['created_at'] = $iat;
$res['expire_at'] = $iat + 86400;

// create Token json
$tokenJson = json_encode($res);
file_put_contents('token.json',$tokenJson);
?>