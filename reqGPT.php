<?php
// message
$question = $_POST['msg'];
// open Ai API key
$OPENAI_API_KEY = "<open Ai API key>";

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
        'content' => $question
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
echo $openAi_json['choices'][0]["message"]["content"];
curl_close($curl_openAi);
?>