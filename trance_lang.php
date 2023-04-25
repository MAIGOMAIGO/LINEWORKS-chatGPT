<?php
// chatGPTに聞くために日本語を英語に直す
$trance_url = '<GASのwebアプリケーションURL>';// GASのwebアプリケーションURL
$msg = $_POST['msg']; // message
$trance_data = json_encode(array(
    'sl' => 'ja', // source language
    'tl' => 'en', // trance language
    'text' => $msg
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

/* ここにchatGPTに聞くプログラムを入れる */

// chatGPTから返ってきた英語を日本語に直す
$retrance_data = json_encode(array(
    'sl' => 'en',
    'tl' => 'ja',
    'text' => $trance_res
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

$retrance_res = curl_exec($retrance_curl); // 送信
curl_close($retrance_curl);
echo $retrance_res;
?>