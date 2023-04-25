<?php
//  get raw data from the request 
$json = file_get_contents('php://input');
// Converts json data into a PHP object 
$data = json_decode($json, true);

$client_id='<Client ID>'; // Client ID
$client_secret='<Client secret ID>'; // Client secret ID
$service_account='<Service Account ID>'; // Service Account ID
$private_key_path='<Private Key Path>'; // Private Key Path
$botId = '<Bot ID>'; // Bot ID
$userId = '<default User ID>'; // default User ID
// open Ai API key
$OPENAI_API_KEY = "<chatGPT API key>"; // chatGPT API key
$trance_url = '<GASのwebアプリケーションURL>';// GASのwebアプリケーションURL

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

// get AccessToken
// Initialize curl session
$curlToken = curl_init();
// Set curl options
curl_setopt($curlToken, CURLOPT_URL, 'https://auth.worksmobile.com/oauth2/v2.0/token'); //set url
curl_setopt($curlToken, CURLOPT_POST, true); //http post
curl_setopt($curlToken, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']); // set header
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
curl_setopt($curlToken, CURLOPT_POSTFIELDS, $body);
curl_setopt($curlToken, CURLOPT_RETURNTRANSFER, true);

// request AccessToken
$res = curl_exec($curlToken);
curl_close($curlToken);
$Token = null;
$ac = json_decode($res,true);
if(isset($ac['access_token'])){
  $Token = $ac['access_token'];
}else{
  echo 'not access_token';
  exit;
}

// get channelId or userId
$channelId = null;
if(isset($data['source']['channelId'])){
  $channelId = $data['source']['channelId'];
}else{
  $userId = $data['source']['userId'];
}

// LineWorks bot message API
$urlUser = "https://www.worksapis.com/v1.0/bots/{$botId}/users/{$userId}/messages";
$urlChannel = "https://www.worksapis.com/v1.0/bots/{$botId}/channels/{$channelId}/messages";

// set url & message
$url = null;
$message = null;
if('message' == $data['type']){
  // message type
  if(isset($data['source']['channelId'])){
    $url = $urlChannel;
    $message = $data['content']['text'];
  }else{
    $url = $urlUser;
    $message = $data['content']['text'];
  }

  // chatGPTに聞くために日本語を英語に直す
  $trance_data = json_encode(array(
      'sl' => 'ja', // source language
      'tl' => 'en', // trance language
      'text' => $message
  ));
  // curl set up
  $trance_curl = curl_init();
  curl_setopt($trance_curl, CURLOPT_URL, $trance_url);
  curl_setopt($trance_curl, CURLOPT_POST, true);
  curl_setopt($trance_curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
  curl_setopt($trance_curl, CURLOPT_POSTFIELDS, $trance_data);
  curl_setopt($trance_curl, CURLOPT_RETURNTRANSFER, true); // レスポンスを文字列として受け取る。
  curl_setopt($trance_curl, CURLOPT_FOLLOWLOCATION, true); // Locationをたどる
  curl_setopt($trance_curl, CURLOPT_MAXREDIRS, 10); // 最大何回リダイレクトをたどるか
  curl_setopt($trance_curl, CURLOPT_AUTOREFERER, true); // リダイレクトの際にヘッダのRefererを自動的に追加させる

  $trance_res = curl_exec($trance_curl); // 送信
  curl_close($trance_curl);

  // chatGPTに質問する
  // set curl
  $curl_openAi = curl_init();
  $headers  = array(
      'Content-Type: application/json',
      "Authorization: Bearer {$OPENAI_API_KEY}"
  );

  // set json data
  $postData = json_encode(array(
      'model' => 'gpt-3.5-turbo',
      'messages' => array(array(
          'role' => 'user',
          'content' => $trance_res
      ))
  ));

  curl_setopt($curl_openAi, CURLOPT_URL, 'https://api.openai.com/v1/chat/completions');
  curl_setopt($curl_openAi, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($curl_openAi, CURLOPT_HTTPHEADER, $headers);
  curl_setopt($curl_openAi, CURLOPT_POST, true);
  curl_setopt($curl_openAi, CURLOPT_POSTFIELDS, $postData);
  curl_setopt($curl_openAi, CURLOPT_SSL_VERIFYPEER, false);
  $result_openAi = curl_exec($curl_openAi);
  // error cheak 
  if($result_openAi === false){
      echo curl_error($curl_openAi);
      exit;
  }

  $openAi_json = json_decode($result_openAi, true);
  curl_close($curl_openAi);

  // chatGPTから返ってきた英語を日本語に直す
  $retrance_data = json_encode(array(
      'sl' => 'en',
      'tl' => 'ja',
      'text' => $openAi_json['choices'][0]["message"]["content"]
  ));
  // curl set up
  $retrance_curl = curl_init();
  curl_setopt($retrance_curl, CURLOPT_URL, $trance_url);
  curl_setopt($retrance_curl, CURLOPT_POST, true);
  curl_setopt($retrance_curl, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
  curl_setopt($retrance_curl, CURLOPT_POSTFIELDS, $retrance_data);
  curl_setopt($retrance_curl, CURLOPT_RETURNTRANSFER, true); // レスポンスを文字列として受け取る。
  curl_setopt($retrance_curl, CURLOPT_FOLLOWLOCATION, true); // Locationをたどる
  curl_setopt($retrance_curl, CURLOPT_MAXREDIRS, 10); // 最大何回リダイレクトをたどるか
  curl_setopt($retrance_curl, CURLOPT_AUTOREFERER, true); // リダイレクトの際にヘッダのRefererを自動的に追加させる

  $message = curl_exec($retrance_curl); // 送信
  curl_close($retrance_curl);

}else if('join' == $data['type']){
  // join type
  $url = $urlUser;
  $message = "JOIN EVENTS\nso-nan bot has logged in to the ChannelID : {$channelId}";
}else if('leave' == $data['type']){
  // leave type
  $url = $urlUser;
  $message = "LEAVE EVENTS\nso-nan bot has logged out from the ChannelID : {$channelId}";
}else if('joined' == $data['type']){
  // joined type
  $url = $urlUser;
  $message = "JOINED EVENTS\nA certain member has joined a ChannelID : {$channelId}";
}else if('left' == $data['type']){
  // left type
  $url = $urlUser;
  $message = "LEFT EVENTS\nA certain member has left the ChannelID : {$channelId}";
}else if('postback' == $data['type']){
  // postback type
  $url = $urlUser;
  $message = "POSTBACK EVENTS\nChannelID : {$channelId}";
}else{
  // other type
  $url = $urlUser;
  $message = "OTHER EVENTS\nThe bot is performing an action. channelId:{$channelId}";
}

// body
$data = array(
    'content' => array(
        'type' => 'text',
        'text' => "{$message}"
    )
);
  
// Set request headers
$headers = array(
    'Content-Type: application/json',
    "Authorization: Bearer {$Token}"
);

// Initialize curl session
$curl = curl_init();

// Set curl options
curl_setopt($curl, CURLOPT_URL, $url);
curl_setopt($curl, CURLOPT_POST, true);
curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($curl);

// Get HTTP status code
$httpStatusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);

// Check for errors
if (curl_errno($curl)) {
  echo 'Error: ' . curl_error($curl);
} elseif ($httpStatusCode !== 200) {
  // Get error message from response
  $responseData = json_decode($response, true);
  $errorMessage = $responseData['message'];

  // Print error message
  echo 'Error (' . $httpStatusCode . '): ' . $errorMessage;
} else {
  // Print response
  echo $response;
}
curl_close($curl);
?>