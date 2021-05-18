<?php

/**
 * 简单的谷歌请求封装类。
 * 包含内购一次性商品查询和确认、 firebase 推送和主题加入。
 *
 * 仅供参考，不建议直接投入生产环境使用，请务必按照项目需要进行修改。
 *
 * Author : 吴国章
 * Email : lich.wu2014@gmail.com
 * blog : https://wuguozhang.com
 *
 * Class GoogleIAPService
 */
class GoogleIAPService
{
    protected $googleClientJson;
    protected $googleClientDefaultAccessToken;
    protected $fcmAPIKey;
    protected $firebaseProjectId;

    /**
     * GoogleIAPService constructor
     * @param string $googleClientJson
     * 谷歌申请 OAuth 客户端ID后下载获得的 JSON ， 正常情况下，JSON 文件下载回来之后都无需修改。 例：
     *
     * {
    "web": {
    "client_id": "谷歌提供",
    "project_id": "谷歌提供",
    "auth_uri": "https://accounts.google.com/o/oauth2/auth",
    "token_uri": "https://oauth2.googleapis.com/token",
    "auth_provider_x509_cert_url": "https://www.googleapis.com/oauth2/v1/certs",
    "client_secret": "谷歌提供",
    "redirect_uris": [
    "项目配置"
    ],
    "javascript_origins": [
    "项目配置"
    ]
    }
    }
     *
     * @param string $googleClientDefaultAccessToken
     * 通过 OAuth 的授权流程，获取到 refresh_token 时的接口 respond 。 例：
     *
     * {
    "access_token": "ya29.a0Ae4lvC0IGeUxqvfb6N0_wIsv6guP6DdX0yRNstI41kvrrhJ4bu4hptcbSzGudBzZyYAngKmCenb2QWBq5CnxJMvLf5rHGAWSxmPeBMHlu3z-3_i8T32owHMoFoaFKJbK5LkJNDbXZotkl8RQd9hIvsHTbrUWNTjk3NM",
    "expires_in": 3599,
    "refresh_token": "关键数据，妥善保管",
    "scope": "授权范围",
    "token_type": "Bearer"
    }
     *
     * @param string $fcmAPIKey
     * 在 firebase 中创建项目后拿到的 API KEY 。例：
     * AAAAnz0-wBs:APA**********zgc4wqykCdeXskX**********vvHxQVkag44ru2BjS6fY**********n8-F0TldxifREk0OZhHjapLS9ek7pWS_aARH-RsS**********WGwUHbiXsQjvqHnmk_op4k
     *
     * @param string $firebaseProjectId
     * 在 firebase 中创建项目后拿到的 PROJECT ID 。例：
     * 683927******
     */
    public function __construct($googleClientJson, $googleClientDefaultAccessToken, $fcmAPIKey, $firebaseProjectId)
    {
        $this->googleClientJson = $googleClientJson;
        $this->googleClientDefaultAccessToken = $googleClientDefaultAccessToken;
        $this->fcmAPIKey = $fcmAPIKey;
        $this->firebaseProjectId = $firebaseProjectId;
    }

    /**
     * 获取Google OAuth Access Token
     *
     * @param array $googleConfig   谷歌配置数组
     * @param bool $forceRefresh
     * @return string
     */
    public function getToken($googleConfig, $forceRefresh = false)
    {
        $clientConfigJson = $googleConfig['googleClientJson'];
        $clientConfigArray = json_decode($clientConfigJson, true);
        $clientId = $clientConfigArray['web']['client_id'];
        $clientSecret = $clientConfigArray['web']['client_secret'];
        $defaultAccessTokenArray = json_decode($googleConfig['googleClientDefaultAccessToken'], true);
        $refreshToken = $defaultAccessTokenArray['refresh_token'];

        // 从redis获取access_token ，如果不存在则默认已过期
        $accessTokenInfo = $this->getAccessTokenFromCache($clientId);
        if (empty($accessTokenInfo) || $forceRefresh === true) {
            // 请求谷歌服务器，使用refresh
            $accessTokenInfo = $this->fetchAccessTokenWithRefreshToken($refreshToken, $clientId, $clientSecret);
            $accessTokenInfoArray = json_decode($accessTokenInfo, true);
            if (empty($accessTokenInfo) || !is_array($accessTokenInfoArray)) {
                // todo 按需处理异常抛出
                return false;
            }
            $this->setAccessTokenFromCache($clientId, $accessTokenInfoArray);
        } else {
            $accessTokenInfoArray = json_decode($accessTokenInfo, true);
        }

        return $accessTokenInfoArray['access_token'];
    }

    /**
     * @return array
     */
    private function getOAuth2Config()
    {
        $googleClientJson = $this->googleClientJson;
        $googleClientDefaultAccessToken = $this->googleClientDefaultAccessToken;
        $fcmAPIKey = $this->fcmAPIKey;

        return compact('googleClientJson', 'googleClientDefaultAccessToken', 'fcmAPIKey');
    }

    /**
     * 获取订单状态
     *
     * api respond resources
     * https://developers.google.com/android-publisher/api-ref/purchases/products#resource
     *
     * @param string $appPackageName    应用包名
     * @param string $token 客户端执行购买完成后获得的支付令牌
     * @param string $productId 购买的商品id
     * @return bool|string
     * @throws GoogleServiceException
     */
    public function getOrderInfo($appPackageName, $token, $productId)
    {
        $accessToken = $this->getToken($this->getOAuth2Config());
        $packageName = $appPackageName;


        $headerArray = [];

        $url = "https://www.googleapis.com/androidpublisher/v3/applications/{$packageName}/purchases/products/{$productId}/tokens/{$token}?access_token={$accessToken}";
        $curl =curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);    // todo 按需修改超时秒数
        curl_setopt($curl, CURLOPT_RETURNTRANSFER,1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headerArray);
        $res_json = curl_exec($curl);
        $errno = curl_errno($curl);
        $httpCode = curl_getinfo($curl,CURLINFO_HTTP_CODE);
        if ($errno != 0) {
            // todo 按需进行异常处理
            return false;
        }
        curl_close($curl);

        return compact('res_json', 'httpCode');
    }

    /**
     * 从缓存中获取access_token
     *
     * @param $clientId
     * @return bool|mixed|string
     */
    public function getAccessTokenFromCache($clientId)
    {
        // todo 按需填充代码，可用 redis 处理
        return false;
    }

    /**
     * 缓存存储 access_token
     *
     * @param $clientId
     * @param string $accessTokenInfo
     * @return bool|mixed|string
     */
    public function setAccessTokenFromCache($clientId, $accessTokenInfo)
    {
        // todo 按需填充代码，可用 redis 处理
        return false;
    }

    /**
     * 根据 refreshToken 获取 access_token
     *
     * @param $refreshToken
     * @param $clientId
     * @param $clientSecret
     * @return bool|string
     */
    public function fetchAccessTokenWithRefreshToken($refreshToken, $clientId, $clientSecret)
    {
        $postBody = [
            'grant_type' => 'refresh_token',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'refresh_token' => $refreshToken,
        ];
        $headerArray = [];
        $headerArray[] = 'Content-type: application/json;charset=UTF-8';

        $url = 'https://accounts.google.com/o/oauth2/token';

        $curl =curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST,1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($postBody));
        curl_setopt($curl, CURLOPT_TIMEOUT, 5);    // todo 按需修改超时秒数
        curl_setopt($curl, CURLOPT_RETURNTRANSFER,1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headerArray);
        $res_json = curl_exec($curl);
        $errno = curl_errno($curl);
        $httpCode = curl_getinfo($curl,CURLINFO_HTTP_CODE);
        if ($errno != 0) {
            // todo 按需进行异常处理
            return false;
        }
        curl_close($curl);

        return $res_json;
    }

    /**
     * 核销订单
     * https://developers.google.com/android-publisher/api-ref/purchases/products/acknowledge
     *
     * @param string $token
     * @param string $productId
     * @param string $developPayload
     * @return mixed
     */
    public function acknowledgeOrder($token, $productId, $developPayload)
    {
        $accessToken = $this->getToken($this->getOAuth2Config());
        $packageName = self::PACKAGE_NAME;


        $postBody = [
            'developerPayload' => $developPayload,
        ];
        $headerArray = [];
        $headerArray[] = 'Content-type: application/json;charset=UTF-8';
        $headerArray[] = 'Host:googleapis.transmit.com';

        $url = "https://www.googleapis.com/androidpublisher/v3/applications/{$packageName}/purchases/products/{$productId}/tokens/{$token}:acknowledge?access_token={$accessToken}";
        $url = "http://ec2-54-179-190-242.ap-southeast-1.compute.amazonaws.com:8079/androidpublisher/v3/applications/{$packageName}/purchases/products/{$productId}/tokens/{$token}:acknowledge?access_token={$accessToken}";

        $curl =curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST,1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($postBody));
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);    // todo 测试，延长到10秒
        curl_setopt($curl, CURLOPT_RETURNTRANSFER,1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headerArray);
        $httpRespond = curl_exec($curl);
        $errno = curl_errno($curl);
        $httpCode = curl_getinfo($curl,CURLINFO_HTTP_CODE);
        if ($errno != 0) {
            TraceLog::warn("curl请求失败;url:{$url};errno:{$errno};httpCode:{$httpCode}"." ,curl_info:".json_encode(curl_getinfo($curl)),'alarm_observation');
        }
        curl_close($curl);

        return compact('httpRespond', 'httpCode');
    }

    /**
     * body : message
     * https://firebase.google.com/docs/reference/fcm/rest/v1/projects.messages#resource:-message
     *
     * api document :
     * https://firebase.google.com/docs/cloud-messaging/send-message#send_messages_to_topics
     *
     * @param $deviceToken
     * @param $body
     * @param $title
     * @param array $data
     * @return array
     */
    public function sendNotificationMessageToToken($deviceToken, $body, $title, $data = [])
    {
        $accessToken = $this->getToken($this->getOAuth2Config());
        $projectId = $this->firebaseProjectId;

        $postBody = [
            'message' => [
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'token' => $deviceToken,
            ]
        ];
        if (!empty($data)) {
            $postBody['message']['data'] = $data;
        }
        $headerArray = [];
        $headerArray[] = 'Content-type: application/json;charset=UTF-8';
        $headerArray[] = "Authorization: Bearer $accessToken";

        $url = "https://fcm.googleapis.com/v1/projects/$projectId/messages:send";

        $curl =curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST,1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($postBody));
        curl_setopt($curl, CURLOPT_TIMEOUT, 5);    // todo 按需修改超时秒数
        curl_setopt($curl, CURLOPT_RETURNTRANSFER,1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headerArray);
        $httpRespond = curl_exec($curl);
        $errno = curl_errno($curl);
        $httpCode = curl_getinfo($curl,CURLINFO_HTTP_CODE);
        if ($errno != 0) {
            // todo 按需处理异常
            return false;
        }
        curl_close($curl);

        return compact('httpRespond', 'httpCode');
    }

    /**
     * 通过主题，群发推送
     * https://firebase.google.com/docs/cloud-messaging/send-message#send_messages_to_topics
     *
     * @param $topic
     * @param $body
     * @param $title
     * @param $data
     * @return array
     */
    public function sendNotificationMessageToTopic($topic, $body, $title, $data)
    {
        $accessToken = $this->getToken($this->getOAuth2Config());
        $projectId = $this->firebaseProjectId;

        $postBody = [
            'message' => [
                'notification' => [
                    'title' => $title,
                    'body' => $body,
                ],
                'topic' => $topic,
            ]
        ];
        if (!empty($data)) {
            $postBody['message']['data'] = $data;
        }
        $headerArray = [];
        $headerArray[] = 'Content-type: application/json;charset=UTF-8';
        $headerArray[] = "Authorization: Bearer $accessToken";

        $url = "https://fcm.googleapis.com/v1/projects/$projectId/messages:send";

        $curl =curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST,1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($postBody));
        curl_setopt($curl, CURLOPT_TIMEOUT, 5);    // todo 按需修改超时秒数
        curl_setopt($curl, CURLOPT_RETURNTRANSFER,1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headerArray);
        $httpRespond = curl_exec($curl);
        $errno = curl_errno($curl);
        $httpCode = curl_getinfo($curl,CURLINFO_HTTP_CODE);
        if ($errno != 0) {
            // todo 按需处理异常
            return false;
        }
        curl_close($curl);

        return compact('httpRespond', 'httpCode');
    }

    /**
     * 把设备添加到指定主题中
     *
     * @param $deviceToken
     * @param $topic
     * @return array
     */
    public static function addDeviceToTopic($deviceToken, $topic)
    {
        $googleApiServiceObj = new GoogleIAPService();
        $configArray = $googleApiServiceObj->getOAuth2Config();
        $apiKey = $configArray['fcmAPIKey'];

        $headerArray = [];
        $headerArray[] = 'Content-Type:application/json';
        $headerArray[] = "Authorization:key=$apiKey";
        $headerArray[] = "Content-Length: 0";

        $url = "https://iid.googleapis.com/iid/v1/{$deviceToken}/rel/topics/{$topic}";

        $curl =curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_POST,1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, '');
        curl_setopt($curl, CURLOPT_TIMEOUT, 5);    // todo 按需修改超时秒数
        curl_setopt($curl, CURLOPT_RETURNTRANSFER,1);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headerArray);
        $httpRespond = curl_exec($curl);
        $errno = curl_errno($curl);
        $httpCode = curl_getinfo($curl,CURLINFO_HTTP_CODE);
        if ($errno != 0) {
            // todo 按需处理异常
            return false;
        }
        curl_close($curl);

        return compact('httpRespond', 'httpCode');
    }
}
