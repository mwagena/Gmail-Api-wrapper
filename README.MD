# Gmail API wrapper for Laravel 5.x


Setup the api:
## Step 1: Turn on the Gmail API
+ Use [this](https://console.developers.google.com/flows/enableapi?apiid=gmail&pli=1) wizard to create or select a project in the Google Developers Console and automatically turn on the API. Click**Continue**, then**Go to credentials**.
+ On the**Add credentials**to your project page, click the**Cancel**button.
+ At the top of the page, select the**OAuth consent screen**tab. Select an**Email address**, enter a**Product name**if not already set, and click the**Save**button.
+ Select the**Credentials**tab, click the**Create credentials**button and select**OAuth client ID**.
+ Select the application type **webapp**, enter the name of your choosings, fill out the redirect uri to your project and click the**Create button**.
+ You will now receive a **Client-ID** and **Client secret**, copy and paste the settings somewere save since you will be needing these later.


## Step 2: Sign in with the Client-ID and secret
Initiate the Gmail class
```
$gmail = new Gmail();
```
```
$gmail->getAuthUrl();
```
Use the returned code from the previous call to create a access + refresh token 
```
$gmail->makeAccessToken($code);
```

## Step 3: make use of it all
Initiate connection
```
$gmail = MartijnWagena\Gmail\Mail::create()
            ->setAccessToken($access_token, $refresh_token)
            ->setStartDate($date);
```
Check if token refresh needed
 
```
$gmail->isAccessTokenExpired();
```

Fetch all messages starting from the 'setStartDate',
you only receive the ids of the messages
```
$gmail->fetch();
```
 
To fetch details of a message, use the code below
```
$gmail->getMessage($id, $threadId);
```
Send mails with the api
```
$gmail = MartijnWagena\Gmail\Send::create()->setAccessToken($access_token, $refresh_token);

$gmail->sendMail(
    [['email' => 'name@mail.com']],
    [['email' => 'name2@mail.com']],
    [['email' => 'name3@mail.com']],
    'your subject',
    'your message body',
    'inreplytomessageId',
    [] // array of attachments of type File
);
```

## Step 5: If wanted, revoke access
```
$gmail = MartijnWagena\Gmail::create()->setAccessToken($access_token, $refresh_token);
$gmail->revokeAccess();
```