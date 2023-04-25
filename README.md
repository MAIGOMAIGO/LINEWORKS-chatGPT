# LINEWORKS × chatGPT

---

## 目次

- [LINEWORKS × chatGPT](#lineworks--chatgpt)
  - [目次](#目次)
  - [概要](#概要)
  - [環境](#環境)
  - [ディレクトリ構成](#ディレクトリ構成)
  - [シーケンスダイアグラム](#シーケンスダイアグラム)
  - [導入方法](#導入方法)
    - [botを作ってLINEWORKSに招待する](#botを作ってlineworksに招待する)
    - [GASの準備](#gasの準備)
    - [openAi API準備](#openai-api準備)
    - [サーバー側準備](#サーバー側準備)
  - [使用方法](#使用方法)
  - [テスト方法](#テスト方法)
  - [参考資料](#参考資料)

---

## 概要

LINEWORKSとchatGPTの連携を試してみた。
LINEWORKSのbotを利用してchatGPTをLINEWORKSで使えるようにした。

## 環境

| サーバー環境 | 言語 | LINE WORKS API | chatGPT       |
| :-----------: | :--: | :------------: | ------------- |
| Apache/2.4.54 | php |      2.0      | gpt-3.5-turbo |

## ディレクトリ構成

```
test/
　　┣.htaccess
　　┣get_token.php
　　┣index.php
　　┣private_XXXXXXXXXXXXXX.key
　　┣reqGPT.php
　　┣trance_lang.php
　　┗yamabiko.php
```

- .htaccess：ウェブサーバーの設定を定義するファイルです。testを外から見えないようにするためなので各自サーバーに合わせて変更してください。
- get_token.php：JWTを作成し、AccessTokenを取得するプログラムです。
- index.php：メインのphpファイルであり、実際の動作ではこれだけしか動かしません。
- private_XXXXXXXXXXXXXX.key：LINEWORKS Developer Consoleから取得してきたPrivateKeyです。
- reqGPT.php：chatGPTに質問するプログラムです。
- trance_lang.php：質問と返答の言語を翻訳するプログラムです。
- yamabiko.php：LINEWORKSのbotにコメントさせるプログラムです。


## シーケンスダイアグラム

![シーケンスダイアグラム](images/lineworks_bot.drawio.png "シーケンスダイアグラム")

Token節約のためGASによる翻訳作業がchatGPTの処理の前後にくる。
AccessTokenは24時間有効なのでどこかで保存しておけばわざわざ毎回処理する必要はないが、今回は特に気にしないのでそのまま入れた。

## 導入方法

前提として以下の事が出来ている状況とする。

- LINEWORKSでアカウント作成済みであり、Bot追加が可能である。
- BotのCallBackを受けるためのサーバーがある。
- Googleアカウントを持っている。
- chatGPTを使うためのアカウントを持っている。

### botを作ってLINEWORKSに招待する

まずはLINEWORKS Developer Consoleでアプリを作成します。

LINEWORKS Developer Console
[https://developers.worksmobile.com/jp/console/openapi/v2/app/list/view](https://developers.worksmobile.com/jp/console/openapi/v2/app/list/view)

![LINEWORKS Developer Console](images/LINEdevcon.png "編集済みLINEWORKS")

アプリの新規作成からアプリを作成します。  
アプリ名、アプリの説明は適当に記入。  
RedirectURLは記入せず、OAuth Scopesにbot,bot.read,user.readを追加して保存する。

できたらアプリを開きます。
![LINEWORKSアプリ](images/LINEapp.png "編集済みLINEapp")

Service Accountの発行ボタンを押すとServiceAccountが発行されPrivate Keyが発行できるようになる。Private Keyを発行するとprivate_XXXXXXXXXXXXXX.keyがダウンロードされるのでなくさないように保管する。  
後でClient ID、Client Secret、Service Account、Private Keyは使います。

つぎにBotを用意します。

LINEWORKS Developer Console の botページ
[https://developers.worksmobile.com/jp/console/bot/view](https://developers.worksmobile.com/jp/console/bot/view)

![LINEWORKSbot](images/LINEWORKSbot.png "編集済みsounan_bot画像")

登録ボタンを押して新しくbotを作ります。

![bot](images/createBot.png "編集済みbot作成")

bot名、説明は適当。API InterfaceはAPI2.0を選択。  
CallbackはOn メッセージタイプはテキストだけ選択。  
複数人のトークルームに招待可にチェックをつける。  
管理者の主担当に自分を検索して追加する。  
全部できたら保存。  
Bot IDは後で使います。

つぎはLINEWORKSにbotを招待します。

![addBot](images/addBot.png "bot 追加")

LINEWORKSの管理者画面で「サービス ＞ Bot」からBotを追加する。追加後は、Bot詳細から公開設定を行い、テナントユーザーが使えるようにする。

### GASの準備

Token節約用のGoogle App Script(GAS)を作成します。

Google App Script
[https://script.google.com/home](https://script.google.com/home)
開けなかったらこちらを参考に使えるようにしてください。
[https://note.com/koushikagawa/n/n04aed663361f](https://note.com/koushikagawa/n/n04aed663361f)

![GAS](images/GASfile.png "GAS project")

プロジェクト名は適当で、ファイル名はmain.gsです。ソースコードは以下のようになります。

```javascript
function doPost(e) {
  // const text = e.postData.getDataAsString();
  const data = JSON.parse(e.postData.getDataAsString());
  const text = data.text;
  const sl = data.sl; // Source language
  const tl = data.tl; // translation language
  const translatedText = LanguageApp.translate(text, sl, tl);

  const output = ContentService.createTextOutput();
  output.setMimeType(ContentService.MimeType.TEXT);
  output.setContent(translatedText);
  return output;
}
```

できたらデプロイを押して新しいデプロイを選択します。
種類はウェブアプリ
説明文は無くても大丈夫です。
次のユーザーとして実行は自分を選び、アクセスできるユーザーは全員にします。

後でウェブアプリのURLを使います。デプロイを管理からもコピーしにいけます。

### openAi API準備

openAiのAPIを使うためにkeyを取得します。

こちらでCreate new secret keyを押すと生成できます。
[https://platform.openai.com/account/api-keys](https://platform.openai.com/account/api-keys)

![openAikey](images/openAiKey.png "API key")
keyは後で使います。

### サーバー側準備

Callbackを受けるサーバー側の準備をしていきます。
.htaccessはサーバーの構成に合わせて適宜作成してください。

このページからindex.phpを使用するので、コピペでもダウンロードでもいいのでサーバーの中に用意してください。
[https://github.com/MAIGOMAIGO/LINEWORKS-chatGPT](https://github.com/MAIGOMAIGO/LINEWORKS-chatGPT)

index.phpを入れる場所はpost通信が通る場所において下さい。一緒にprivate_XXXXXXXXXXXXXX.keyも入れて下さい。一緒が嫌ならindex.phpからpathを指定して参照出来るところにしてください。

index.phpを編集していきます。

```php
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

// 以下略
```

- client_idからtrance_urlまでを自分のに変更します。  
- Client ID、Client secret ID、Service Account IDはLINEWORKS Developer Consoleのアプリで作成したものです。  
- Private Key Pathはサーバー側の準備でindex.phpと一緒に持ってきた位置です。同じディレクトリならそのままファイル名を書けばOKです。  
- Bot IDはLINEWORKS Deveroper ConsoleのBotに書いてあります。  
- userIdで指定した人に招待や退出時の通知が行きます。LINEWORKSアカウントの個人情報のIDがuserIdになります。  
- chatGPT API keyはopenAi API準備で手に入れたkeyです。  
- GASのwebアプリケーションURLはGASの準備で用意したものです。

以上で準備が終わりました。試しにトークにbotを追加してみて喋りかけて見てください。
返答があれば成功です。

返答が来ない場合は[テスト方法](#テスト方法)を試してみてください。原因を探すことができます。

## 使用方法

まだ未完成

## テスト方法

まだ未完成

## 参考資料

- 導入方法のbot作成に参考にしたURL
[https://qiita.com/mmclsntr/items/eee8d8f3546410fe6652](https://qiita.com/mmclsntr/items/eee8d8f3546410fe6652)
- LINEWORKS Developer APIの利用
[https://developers.worksmobile.com/jp/reference/client-app?lang=ja](https://developers.worksmobile.com/jp/reference/client-app?lang=ja)
- LINEWORKS Developer Bot
[https://developers.worksmobile.com/jp/reference/bot?lang=ja](https://developers.worksmobile.com/jp/reference/bot?lang=ja)
