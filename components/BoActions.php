<?php

namespace app\components;

use app\models\Accounts;
use app\models\AccountsTransactions;
use app\models\BoTrades;
use Yii;
use yii\base\Component;

class BoActions extends Component
{
    public $host;
    public $api_user;
    public $auth_key;

    private $aFunctions = array(
        'createAccount'  => 'acc-create',
        'getBalance'     => 'get-balance',
        'getDeals'       => 'get-deals',
        'getOpenedDeals' => 'get-opened-deals',
        'getApikey'      => 'get-apikey',
        'makePayment'    => 'fin-operation',
        'refreshDeals'   => 'get-new-closed-deals'
    );

    private $ParamsJson;
    private $aErrors;

    private function stringifyRequest($data)
    {
        return implode('&', array_map(
            function ($v, $k) {
                return sprintf("%s=%s", $k, $v);
            },
            $data, array_keys($data)
        ));
    }

    public function createAccount($params = [])
    {
        if (empty($params['name']) || empty($params['password']) || empty($params['email']) || empty($params['country'])
            || empty($params['city']) || empty($params['address']) || empty($params['phone_password'])
            || empty($params['zip_code']) || empty($params['user_id']) || empty($params['ip']) || empty($params['leverage'])
        ) {
            var_dump($params);
            trigger_error("BoActions::createAccount incorrect input parameters", E_USER_WARNING);
            return ['account' => false];
        }

        $aData = [
            'acc_type' => $params['group'],
        ];

        $aResult = $this->sendPostRequest($this->aFunctions[__FUNCTION__], $aData);

        return [
            'account'  => (!empty($aResult['acc_id']) ? $aResult['acc_id'] : false),
            'settings' => '',
        ];
    }

    /**
     * @param array $params
     *      'login'     => $account->foreign_account,
     *      'amount'    => $accountTransaction->amount,
     *      'comment'   => $accountTransaction->comment,
     * @return bool|mixed
     */
    public function makePayment($params = [])
    {
        if (empty($params['login']) || empty($params['amount']) || empty($params['comment'])) {
            trigger_error("BoActions::makePayment incorrect input parameters", E_USER_WARNING);
            return false;
        }
        $account = Accounts::find()->where(['foreign_account' => $params['login']])->andWhere(['in', 'account_type', [29, 30]])->one();

        $result = $this->refreshBalance($params['login']);
        if (!$result['status']) {
            return false;
        }

        $response = $this->sendPostRequest($this->aFunctions[__FUNCTION__], $params);
        if(!$response['status']){
            return false;
        }

        $result = $this->refreshBalance($params['login']);
        if (!$result['status']) {
            return false;
        }
        if(isset($result['balance']) && $account){
            $query = "UPDATE accounts SET balance = {$result['balance']} WHERE id = {$account->id}";
            Yii::$app->db->createCommand($query)->execute();
        }

        return $response['status'];
    }

    public function getBalance($aParams)
    {
        return $this->sendGetRequest($this->aFunctions[__FUNCTION__], $aParams);
    }

    public function refreshDeals($aParams)
    {
        return $this->sendGetRequest($this->aFunctions[__FUNCTION__], $aParams);
    }

    public function getDeals($aParams)
    {
        return $this->sendGetRequest($this->aFunctions[__FUNCTION__], $aParams);
    }

    public function getOpenedDeals($aParams)
    {
        return $this->sendGetRequest($this->aFunctions[__FUNCTION__], $aParams);
    }

    public function getApikey($aParams)
    {
        return $this->sendGetRequest($this->aFunctions[__FUNCTION__], $aParams);
    }

    public function finOperation($aParams)
    {
        return $this->sendPostRequest($this->aFunctions[__FUNCTION__], $aParams);
    }

    private function addSignToRequest($data)
    {
        $data['user'] = $this->api_user;
        $data['sign'] = $this->auth_key;
        $data['stamp'] = (string)time();
        $data['sign'] = $this->generateSign($data);
        return $data;
    }

    private function sendPostRequest($sAction, $aParams)
    {
        $aFields = array(
            'query'    => json_encode([
                'url'  => "{$this->host}{$sAction}",
                'data' => $aParams,
            ]),
            'status'   => 0,
            'response' => '',
        );

        $this->ParamsJson = json_encode($aParams, JSON_UNESCAPED_UNICODE);
        $params = $this->addSignToRequest($aParams);
        $params = $this->stringifyRequest($params);
        $aAddonHeaders = array(
            'Content-Length: ' . strlen($params),
        );
        $rCURL = curl_init();
        $sUrl = $this->host . $sAction;
        curl_setopt($rCURL, CURLOPT_URL, $sUrl);
        curl_setopt($rCURL, CURLOPT_POST, true);
        curl_setopt($rCURL, CURLOPT_POSTFIELDS, $params);
        curl_setopt($rCURL, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($rCURL, CURLOPT_HTTPHEADER, $aAddonHeaders);
        $sResponse = $aFields['response'] = curl_exec($rCURL);

        curl_close($rCURL);
        $aResponse = json_decode($sResponse, true);

        Yii::$app->db->createCommand()->insert('accounts_boactions_log', $aFields)->execute();

        return $aResponse;

    }

    private function sendGetRequest($sAction, $aParams)
    {
        $aFields = array(
            'query'    => json_encode([
                'url'  => "{$this->host}{$sAction}",
                'data' => $aParams,
            ]),
            'status'   => 0,
            'response' => '',
        );

        $this->ParamsJson = json_encode($aParams, JSON_UNESCAPED_UNICODE);
        $params = $this->addSignToRequest($aParams);
        $params = $this->stringifyRequest($params);
        $rCURL = curl_init();
        $sUrl = $this->host . $sAction . '/' . $aParams['acc_id'] . '?' . $params;
        curl_setopt($rCURL, CURLOPT_URL, $sUrl);
        curl_setopt($rCURL, CURLOPT_RETURNTRANSFER, true);
        $sResponse = $aFields['response'] = curl_exec($rCURL);

        curl_close($rCURL);
        $aResponse = json_decode($sResponse, true);

        Yii::$app->db->createCommand()->insert('accounts_boactions_log', $aFields)->execute();

        return $aResponse;
    }

    public function generateSign($data)
    {
        if (is_array($data)) {
            ksort($data);
            $sign = substr(substr(md5(json_encode($data)), 1), 0, -1);
            return $sign;
        }
        return 0;
    }

    /**
     * Получение маржевых данных счета
     *
     * @param array $params
     *      int account_id
     *
     * @return array|bool
     *        float margin маржа
     *        float free_margin (средства-маржа)
     *        float equity средства
     */
    function getTradesMarginInfo($params = [])
    {
        if (empty($params['account_id']) || !is_numeric($params['account_id'])) {
            trigger_error("WebActions::getTradesMarginInfo incorrect input parameters", E_USER_WARNING);
            return false;
        }

        /**
         * @var $account Accounts
         */
        $account = Accounts::findOne($params['account_id']);
        if ($account == null) {
            trigger_error("BoActions::getTradesMarginInfo account not found", E_USER_WARNING);
            return false;
        }

        $aTradesMarginInfo = array(
            'margin' => $account->balance,
            'free'   => $account->balance,
            'equity' => $account->balance,
        );

        return $aTradesMarginInfo;
    }

    public function refreshBalance($login)
    {
        $status = $balance = false;
        $params = [
            'acc_id' => $login,
        ];
        $result = $this->refreshDeals($params);
        if ($result['status']) {
            $this->addBoTransactions($result['deals']);
        }
        $resultBalance = $this->getBalance($params);
        if ($resultBalance['status'] && isset($resultBalance['balance'])) {
            if (bccomp($resultBalance['balance'], $this->calculateBoBalance($login), 2) === 0) {
                $status = true;
                $balance = $resultBalance['balance'];
            }
        }
        return [
            'status'  => $status,
            'balance' => $balance
        ];
    }

    public function addBoTransactions($deals)
    {
        if (is_array($deals)) {
            foreach ($deals as $deal) {
                if (isset($deal['id']) && isset($deal['account_id']) && isset($deal['status']) && isset($deal['symbol']) && isset($deal['cmd']) && isset($deal['open_price']) && isset($deal['close_price']) && isset($deal['bet']) && isset($deal['profit']) && isset($deal['take_profit']) && isset($deal['stop_loss']) && isset($deal['open_time']) && isset($deal['close_time']) && isset($deal['balance'])) {
                        $transaction = BoTrades::findOne(['id' => $deal['id']]);
                    if (!$transaction) {
                        //Если транзакции с таким ID не существует - то создаём новую
                        $transaction = new BoTrades($deal);
                        $transaction->amount = $deal['profit'] - $deal['bet'];
                        if (!$transaction->save()) {
                            return false;
                        };
                    } elseif ($transaction->cmd != 'fin' && $transaction->close_price == 0) {
                        //Если же транзакция с таким ID существует, но она проходит по базе как открытая - то перезаписываем
                        $transaction->account_id = $deal['account_id'];
                        $transaction->status = $deal['status'];
                        $transaction->symbol = $deal['symbol'];
                        $transaction->cmd = $deal['cmd'];
                        $transaction->open_price = $deal['open_price'];
                        $transaction->close_price = $deal['close_price'];
                        $transaction->bet = $deal['bet'];
                        $transaction->profit = $deal['profit'];
                        $transaction->take_profit = $deal['take_profit'];
                        $transaction->stop_loss = $deal['stop_loss'];
                        $transaction->open_time = $deal['open_time'];
                        $transaction->close_time = $deal['close_time'];
                        $transaction->balance = $deal['balance'];
                        $transaction->amount = $deal['profit'] - $deal['bet'];
                        if (!$transaction->save()) {
                            return false;
                        };
                    }
                }
            }
        }
        return true;
    }

    public function calculateBoBalance($login)
    {
        $query = BoTrades::find()->where(['account_id' => $login]);
        if ($query->exists()) {
            return $query->sum('amount');
        } else {
            return 0;
        }
    }

    function сhangePass($params = [])
    {
       return false;
    }
}
