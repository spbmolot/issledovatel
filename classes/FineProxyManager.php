
<?php



class FineProxyManager {

    private $login = 'PrsUS4BKCKXW';

    private $password = '1qLzxWlh';

    private $baseUrl = 'https://fineproxy.org/api/getproxy/';

    

    public function getServerIP() {

        // Пробуем разные способы получения внешнего IP сервера

        $methods = array(

            'https://api.ipify.org',

            'https://ipinfo.io/ip',

            'https://icanhazip.com',

            'https://ifconfig.me/ip'

        );

        

        foreach ($methods as $url) {

            try {

                $ch = curl_init();

                curl_setopt_array($ch, array(

                    CURLOPT_URL => $url,

                    CURLOPT_RETURNTRANSFER => true,

                    CURLOPT_TIMEOUT => 5,

                    CURLOPT_USERAGENT => 'Mozilla/5.0'

                ));

                

                $ip = curl_exec($ch);

                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

                curl_close($ch);

                

                if ($httpCode === 200 && filter_var(trim($ip), FILTER_VALIDATE_IP)) {

                    return trim($ip);

                }

            } catch (Exception $e) {

                continue;

            }

        }

        

        // Резервный способ - IP сайта

        return '176.57.216.68';

    }

    

    public function setIP($ip = null) {

        if ($ip === null) {

            $ip = $this->getServerIP();

        }

        

        $url = $this->baseUrl . '?action=setip&login=' . $this->login . '&password=' . $this->password . '&ip=' . $ip;

        

        $ch = curl_init();

        curl_setopt_array($ch, array(

            CURLOPT_URL => $url,

            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_TIMEOUT => 10,

            CURLOPT_FOLLOWLOCATION => true

        ));

        

        $response = curl_exec($ch);

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        

        error_log("FineProxy: Добавляем IP $ip, ответ: $response");

        

        return $httpCode === 200;

    }

    

    public function getBestProxy() {

        // Добавляем правильный IP сервера

        $this->setIP('176.57.216.68');

        

        return 'proxy1.fineproxy.org:8085';

    }

    

    public function testProxy($proxyString) {

        $ch = curl_init();

        curl_setopt_array($ch, array(

            CURLOPT_URL => 'https://api.openai.com/v1/models',

            CURLOPT_RETURNTRANSFER => true,

            CURLOPT_PROXY => $proxyString,

            CURLOPT_PROXYTYPE => CURLPROXY_HTTP,

            CURLOPT_TIMEOUT => 10,

            CURLOPT_HTTPHEADER => array(

                'Authorization: Bearer sk-test-key',

                'Content-Type: application/json'

            )

        ));

        

        $response = curl_exec($ch);

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        $error = curl_error($ch);

        curl_close($ch);

        

        if ($error) {

            return array('success' => false, 'error' => $error);

        }

        

        return array('success' => ($httpCode == 401 || $httpCode == 200), 'code' => $httpCode);

    }

}

?>

