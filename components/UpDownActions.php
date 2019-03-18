<?php

namespace app\components;

use Yii;
use yii\base\Component;
use app\models\Accounts;

class UpDownActions extends Component
{
    public $host;
    public $apikey;

    private $rFAPI = array();   // Массив подключений

    /**
     * Выполнить соединение/реконнект
     *
     * @param   string  $sServerIP
     * @return  boolean
     */
    private function connect($sServerIP = '')
    {
        if (empty($sServerIP))
        {
            $sServerIP = $this->host;
        }
        if (empty($this->rFAPI[$sServerIP]) || feof($this->rFAPI[$sServerIP]))
        {
            $this->rFAPI[$sServerIP] = @fsockopen($sServerIP, $this->port, $iError, $sError, 10);

            if (!feof($this->rFAPI[$sServerIP]))
            {
                fputs($this->rFAPI[$sServerIP], "W");
            }
        }

        return (!feof($this->rFAPI[$sServerIP]));
    }

    /**
     * Performs request to the server and gets the answer
     *
     * @param   string  $sURL
     * @param   array   $aData
     * @param   string  $sClientAuth
     * @param   bool    $bPOST
     * @return  mixed
     */
    private function makeRequest($sURL, $aData = [], $sClientAuth = '', $bPOST = true)
    {
        $sAuthorization = '';
        if (!empty($sClientAuth)) {
            $sAuthorization = "Authorization: Bearer {$sClientAuth}";
        }

        $aFields = array(
            'query'		=> json_encode([
                'url'   => "{$this->host}{$sURL}",
                'data'  => $aData,
            ]),
            'status'	=> 0,
            'response'	=> '',
        );

        $rCURL = curl_init();
        curl_setopt($rCURL, CURLOPT_URL, "{$this->host}{$sURL}");
        curl_setopt($rCURL, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($rCURL, CURLOPT_CONNECTTIMEOUT, 5);
        curl_setopt($rCURL, CURLOPT_TIMEOUT, 30);
        curl_setopt($rCURL, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($rCURL, CURLOPT_SSL_VERIFYPEER, 0);
        if ($bPOST) {
            curl_setopt($rCURL, CURLOPT_POST, true);
            curl_setopt($rCURL, CURLOPT_POSTFIELDS, json_encode($aData));
        } elseif (!empty($aData)) {
            $sQueryParams = http_build_query($aData);
            curl_setopt($rCURL, CURLOPT_URL, "{$this->host}{$sURL}?$sQueryParams");
        }
        curl_setopt($rCURL, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-type: application/json',
            $sAuthorization,
        ]);

        $sResponse = $aFields['response'] = curl_exec($rCURL);
        curl_close($rCURL);
        $aResponse = json_decode($sResponse, true);

        Yii::$app->db->createCommand()->insert('accounts_updownactions_log', $aFields)->execute();

        return $aResponse;
    }

    /**
     * Провести платеж
     *
     * @param array $params
     *      int login
     *      float amount
     *      string comment
     *      int force
     *      int update_user

     * @return boolean
     */
    function makePayment($params)
    {
        if (empty($params['login']) || (empty($params['amount']) && empty($params['force'])))
        {
            trigger_error("UpDownActions::makePayment incorrect input parameters", E_USER_WARNING);
            return false;
        }

        $aResult = false;

        $Account = Accounts::findOne(['foreign_account' => $params['login']]);
        if ($Account != null) {
            $aForeignSettings = json_decode($Account->foreign_settings, true);
            $aData = [
                'amount'  => abs($params['amount']),
                'comment' => $params['comment'],
            ];

            $sOperation = (bccomp($params['amount'], 0, 2) == 1) ? "funding" : "withdrawal";
            $sURL = "api/v1/account/{$Account->foreign_account}/{$sOperation}/";

            $aResult = $this->makeRequest($sURL, $aData, $aForeignSettings['access_token']['access_token']);
            if (!$aResult || (isset($aResult['status']) && $aResult['status'] != "ok"))
            {
                $aResult = false;
            }
        }

        return !empty($aResult)?true:false;
    }

    /**
     * Провести трансфер
     *
     * @param int $iFrom
     * @param int $iTo
     * @param float $fValue
     * @param string $sCommentFrom
     * @param string $sCommentTo
     * @param int $iForce
     * @param int $iUserUpdate
     *
     * @return boolean
     */
    function makeTransfer($iFrom, $iTo, $fValue, $sCommentFrom, $sCommentTo, $iForce = 0, $iUserUpdate = 0)
    {
        if (empty($iFrom) || empty($iTo) || empty($fValue) || !is_numeric($iFrom) || !is_numeric($iTo) || empty($sCommentFrom) || empty($sCommentTo))
        {
            trigger_error("UpDownActions::makeTransfer incorrect input parameters", E_USER_WARNING);
            return false;
        }

        $sQuery = "CLIENTSTRANSFERBALANCE-MASTER={$this->password}|FROM={$iFrom}|TO={$iTo}|VALUE={$fValue}|COMMENTFROM={$sCommentFrom}|COMMENTTO={$sCommentTo}|FORCE={$iForce}|UPDATEUSER={$iUserUpdate}";
        $sResult = $this->makeRequest($sQuery);

        $iPos = strpos($sResult, "OK\r\n");

        return ($iPos === false || $iPos != 0)?false:true;
    }

    /**
     * Создание нового счета
     *
     * @param array $params:
     *      string group
     *      string name
     *      string password
     *      string investor
     *      string email
     *      string country
     *      string city
     *      string address
     *      string comment
     *      string phone
     *      string phone_password
     *      int zip_code
     *      int ID
     *      int leverage
     *      string ip
     *      string agent
     *      int enabled
     *
     * @return bool|array
     */
    function createAccount($params = [])
    {
        if (empty($params['name']) || empty($params['password']) || empty($params['email']) || empty($params['country'])
         || empty($params['city']) || empty($params['address']) || empty($params['phone']) || empty($params['phone_password'])
         || empty($params['zip_code']) || empty($params['user_id']) || empty($params['ip']) || empty($params['leverage']))
        {
            var_dump($params);
            trigger_error("UpDownActions::createAccount incorrect input parameters", E_USER_WARNING);
            return false;
        }

        $aData = [
            'apikey'        => $this->apikey,
            'country_code'  => 1,
            'phone'         => 1,
            'name'          => $params['name'],
            'email'         => "user_{$params['user_id']}_{$params['account_id']}@privatefx.com",
            'password'      => $params['password'],
        ];

        $aResult = $this->makeRequest('api/v1/registration_verified/', $aData);

        return [
            'account'   => (isset($aResult['account'])?$aResult['account']:false),
            'settings'  => $aResult,
        ];
    }

    /**
     * Получает развернутую информацию о forex счете без ордеров
     *
     * @param int $iLogin
     *
     * @return boolean
     */
    function getUserInfo($iLogin)
    {
        if (empty($iLogin) || !is_numeric($iLogin))
        {
            trigger_error("UpDownActions::getUserInfo incorrect input parameters", E_USER_WARNING);
            return false;
        }

        $sQuery = "CLIENTSUSERINFO-MASTER={$this->password}|LOGIN={$iLogin}";
        $sResult = $this->makeRequest($sQuery);
        $aResult = explode("\r\n", $sResult);
        if ($aResult[0]=="OK")
        {
            $aAccountFields = array(
                'login', 'group', 'enable', 'enable_change_password', 'enable_readonly', 'name', 'country', 'city', 'state', 'zipcode', 'address',
                'phone', 'email', 'comment', 'id', 'status', 'regdate', 'lastdate', 'leverage', 'agent_account', 'balance', 'margin', 'free', 'equity',
                'prevmonthbalance',	'prevbalance', 'credit', 'interestrate', 'taxes', 'prevmonthequity', 'prevequity', 'send_reports'
            );
            array_shift($aResult);
            array_pop($aResult);
            $aAccountInfo = array_combine($aAccountFields, $aResult);
            $aAccountInfo['name']	= iconv('windows-1251', 'UTF-8', $aAccountInfo['name']);
            $aAccountInfo['country']= iconv('windows-1251', 'UTF-8', $aAccountInfo['country']);
            $aAccountInfo['city']	= iconv('windows-1251', 'UTF-8', $aAccountInfo['city']);
            $aAccountInfo['state']	= iconv('windows-1251', 'UTF-8', $aAccountInfo['state']);
            $aAccountInfo['address']= iconv('windows-1251', 'UTF-8', $aAccountInfo['address']);
            $aAccountInfo['comment']= iconv('windows-1251', 'UTF-8', $aAccountInfo['comment']);

            return $aAccountInfo;
        }

        return false;
    }

    /**
     * Проверка пароля
     *
     * @param int $iLogin
     * @param string $sPassword
     * @param int $iInvestor
     *
     * @return boolean
     */
    function сheckPass($iLogin, $sPassword, $iInvestor = 0)
    {
        if (empty($iLogin) || !is_numeric($iLogin) || empty($sPassword))
        {
            trigger_error("UpDownActions::checkPass incorrect input parameters", E_USER_WARNING);
            return false;
        }

        $sQuery = "CLIENTSCHECKPASS-MASTER={$this->password}|LOGIN={$iLogin}|PASSWORD={$sPassword}|INVESTOR={$iInvestor}";
        $sResult = $this->makeRequest($sQuery);
        $aResult = explode("\r\n", $sResult);

        return ($aResult[0]=="OK");
    }

    /**
     * Замена пароля
     *
     * @param array $params
     *      int login
     *      string password
     *      int investor
     *
     * @return boolean
     */
    function сhangePass($params = [])
    {
        if (empty($params['login']) || !is_numeric($params['login']) || empty($params['password']))
        {
            trigger_error("UpDownActions::changePass incorrect input parameters", E_USER_WARNING);
            return false;
        }

        $sQuery = "CLIENTSCHANGEPASS-MASTER={$this->password}|LOGIN={$params['login']}|PASSWORD={$params['password']}|INVESTOR={$params['investor']}|DROPKEY=0";
        $sResult = $this->makeRequest($sQuery);
        $aResult = explode("\r\n", $sResult);

        return ($aResult[0]=="OK");
    }

    /**
     * Обновление данных клиента
     *
     * @param int $iLogin
     * @param string $sUpdates
     *
     * @return bool
     */
    function updateUser($iLogin, $sUpdates)
    {
        if (!isset($iLogin) || !is_numeric($iLogin) || !isset($sUpdates) || strlen($sUpdates)==0)
        {
            trigger_error("UpDownActions::updateUser incorrect input parameters", E_USER_WARNING);
            return false;
        }

        $sQuery = "CLIENTSUSERUPDATE-MASTER={$this->password}|LOGIN={$iLogin}|{$sUpdates}";
        $sResult = $this->makeRequest($sQuery);
        $aResult = explode("\r\n", $sResult);

        return ($aResult[0]=="OK");
    }

    /**
     * Получение маржевых данных счета
     *
     * @param array $params
     *      int account_id
     *
     * @return array|bool
     * 		float margin маржа
     * 		float free_margin (средства-маржа)
     * 		float equity средства
     */
    function getTradesMarginInfo($params = [])
    {
        if (empty($params['account_id']) || !is_numeric($params['account_id'])) {
            trigger_error("UpDownActions::getTradesMarginInfo incorrect input parameters", E_USER_WARNING);
            return false;
        }

        $Account = Accounts::findOne($params['account_id']);
        $aForeignSettings = json_decode($Account->foreign_settings, true);
        $sDemoUri = (!empty($params['demo'])?"demo/":"");
        $sURL = "api/v1/{$sDemoUri}account/{$Account->foreign_account}/summary/USD/";

        $aResult = $this->makeRequest($sURL, [], $aForeignSettings['access_token']['access_token'], false);

        $aTradesMarginInfo = false;
        if (is_array($aResult) && isset($aResult['netAssetValue']))
        {
            $aTradesMarginInfo = [
                'margin'    => $aResult['netAssetValue'],
                'free'      => $aResult['netAssetValue'],
                'equity'    => $aResult['netAssetValue'],
            ];
        }

        return $aTradesMarginInfo;
    }

    /**
     * Получение списка открытых сделок клиента
     *
     * @param int $iLogin
     *
     * @return array
     */
    function getOpenedTrades($iLogin)
    {
        if (empty($iLogin) || !is_numeric($iLogin))
        {
            trigger_error("UpDownActions::getOpenedTrades incorrect input parameters", E_USER_WARNING);
            return false;
        }

        $sQuery = "GETOPENEDORDERS-MASTER={$this->password}|LOGIN={$iLogin}";
        $sResult = $this->makeRequest($sQuery);
        $aResult = explode("\r\n", $sResult);
        if ($aResult[0] == "OK")
        {
            $aTrades = array();
            // Выкидываем из массива ненужное
            array_shift($aResult);
            array_pop($aResult);

            // Составляем фоторобот
            $aTradeFields = array(
                'order', 'login', 'symbol', 'digits', 'cmd', 'volume', 'open_time', 'open_price', 'sl', 'tp', 'close_time', 'expiration', 'conv_rates0',
                'conv_rates1', 'commission', 'commission_agent', 'storage', 'close_price', 'profit', 'taxes', 'comment', 'margin_rate', 'reserved0', 'reserved1', 'reserved2', 'reserved3',
            );

            // Проходимся по всем строкам
            foreach ($aResult as $sTrade)
            {
                $aTrade = explode("\r\n", str_replace("|", "\r\n", $sTrade));
                $aTrade = array_combine($aTradeFields, $aTrade);
                $aTrade['comment'] = mb_convert_encoding($aTrade['comment'], 'UTF-8', 'windows-1251');
                $aTrades[] = $aTrade;
            }

            return $aTrades;
        }

        return false;
    }

    /**
     * Получение количества открытых сделок клиента
     *
     * @param int $iLogin
     *
     * @return array
     */
    function getOpenedTradesCount($iLogin)
    {
        if (empty($iLogin) || !is_numeric($iLogin))
        {
            trigger_error("UpDownActions::getOpenedTradesCount incorrect input parameters", E_USER_WARNING);
            return false;
        }

        $sQuery = "GETOPENEDORDERSCOUNT-MASTER={$this->password}|LOGIN={$iLogin}";
        $sResult = $this->makeRequest($sQuery);
        $aResult = explode("\r\n", $sResult);
        if ($aResult[0] == "OK")
        {
            $aOpenedTradesCount = array(
                'total'     => $aResult[1],
                'opened'    => $aResult[2],
                'pending'   => $aResult[3],
            );
            return $aOpenedTradesCount;
        }

        return false;
    }

    /**
     * Получение списка закрытых сделок клиента за указанный период времени
     *
     * @param int $iLogin
     * @param int $iTimeFrom
     * @param int $iTimeTo
     *
     * @return array
     */
    function getClosedTrades($iLogin, $iTimeFrom, $iTimeTo)
    {
        if (empty($iLogin) || !is_numeric($iLogin) || !isset($iTimeFrom) || !is_numeric($iTimeFrom) || empty($iTimeTo) || !is_numeric($iTimeTo))
        {
            trigger_error("UpDownActions::getClosedTrades incorrect input parameters", E_USER_WARNING);
            return false;
        }

        $sQuery = "GETCLOSEDORDERSBYTIME-MASTER={$this->password}|LOGIN={$iLogin}|TIMEFROM={$iTimeFrom}|TIMETO={$iTimeTo}";
        $sResult = $this->makeRequest($sQuery);
        $aResult = explode("\r\n", $sResult);
        if ($aResult[0] == "OK")
        {
            $aTrades = array();
            // Выкидываем из массива ненужное
            array_shift($aResult);
            array_pop($aResult);

            // Составляем фоторобот
            $aTradeFields = array(
                'order', 'login', 'symbol', 'digits', 'cmd', 'volume', 'open_time', 'open_price', 'sl', 'tp', 'close_time', 'expiration', 'conv_rate1',
                'conv_rate2', 'commission', 'commission_agent', 'storage', 'close_price', 'profit', 'taxes', 'comment', 'margin_rate',
            );
            // Проходимся по всем строкам
            foreach ($aResult as $sTrade)
            {
                $aTrade = explode("\r\n", str_replace("|", "\r\n", $sTrade));
                $aTrade = array_combine($aTradeFields, $aTrade);
                $aTrade['comment'] = mb_convert_encoding($aTrade['comment'], 'UTF-8', 'windows-1251');
                $aTrades[] = $aTrade;
            }

            return $aTrades;
        }

        return false;
    }

    /**
     * Обновление списка закрытых сделок клиента в репликации за указанный период времени
     *
     * @param int $iLogin
     * @param int $iTimeFrom
     * @param int $iTimeTo
     *
     * @return int|bool
     */
    function resyncClosedTrades($iLogin, $iTimeFrom, $iTimeTo)
    {
        if (empty($iLogin) || !is_numeric($iLogin) || !isset($iTimeFrom) || !is_numeric($iTimeFrom) || empty($iTimeTo) || !is_numeric($iTimeTo))
        {
            trigger_error("UpDownActions::getClosedTrades incorrect input parameters", E_USER_WARNING);
            return false;
        }

        $sQuery = "RESYNCCLIENTSCLOSEDORDERS-MASTER={$this->password}|LOGIN={$iLogin}|TIMEFROM={$iTimeFrom}|TIMETO={$iTimeTo}";
        $sResult = $this->makeRequest($sQuery);
        $aResult = explode("\r\n", $sResult);
        if ($aResult[0] == "OK")
        {
            return $aResult[1];
        }

        return false;
    }

    /**
     * Закрывает сделки клиента
     *
     * @param int $iLogin
     *
     * @return boolean
     */
    function closeOrders($iLogin)
    {
        if (empty($iLogin)	|| !is_numeric($iLogin))
        {
            trigger_error("UpDownActions::closeOrders incorrect input parameters", E_USER_WARNING);
            return false;
        }

        $sQuery = "CLOSECLIENTSORDERS-MASTER={$this->password}|LOGIN={$iLogin}";
        $sResult = $this->makeRequest($sQuery);
        $aResult = explode("\r\n", $sResult);

        return ($aResult[0] == "OK");
    }

    /**
     * Получение списка закрытых сделок клиента за указанный период времени
     *
     * @param int $iLogin
     * @param int $iTimeFrom
     * @param int $iTimeTo
     *
     * @return array
     */
    function getDetailedStatement($iLogin, $iTimeFrom, $iTimeTo)
    {
        if (empty($iLogin) || !is_numeric($iLogin) || !isset($iTimeFrom) || !is_numeric($iTimeFrom) || empty($iTimeTo) || !is_numeric($iTimeTo))
        {
            trigger_error("UpDownActions::getDetailedStatement incorrect input parameters", E_USER_WARNING);
            return false;
        }

        $sQuery = "GETCLIENTSSTATEMENT-MASTER={$this->password}|LOGIN={$iLogin}|TIMEFROM={$iTimeFrom}|TIMETO={$iTimeTo}";
        $sResult = $this->makeRequest($sQuery);
        $aResult = explode("\r\n", $sResult);
        if ($aResult[0] == "OK")
        {
            // Выкидываем из массива ненужное
            array_shift($aResult);
            array_pop($aResult);

            // Составляем фоторобот
            $aStatementKeys = array(
                'initial_deposit',
                'summary_profit',
                'gross_profit',
                'gross_loss',
                'profit_factor',
                'expected_payoff',
                'absolute_drawdown',
                'max_drawdown',
                'max_drawdown_percent',
                'rel_drawdown_percent',
                'rel_drawdown',
                'summary_trades',
                'short_trades',
                'short_trades_won',
                'long_trades',
                'long_trades_won',
                'profit_trades',
                'profit_trades_total',
                'loss_trades',
                'loss_trades_total',
                'max_profit',
                'min_profit',
                'avg_profit_trades',
                'avg_loss_trades',
                'con_profit_trades1',
                'con_profit1',
                'con_loss_trades1',
                'con_loss1',
                'con_profit2',
                'con_profit_trades2',
                'con_loss2',
                'con_loss_trades2',
                'avg_con_winners',
                'avg_con_losers',
                'sum_deposit',
                'sum_withdrawal',
                'max_drawdown_ft',
            );

            return array_combine($aStatementKeys, $aResult);
        }

        return false;
    }

    /**
     * Отправляет почту по внутренней почтовой системе MT
     *
     * @param int $iSender
     * @param int $iReceiveTime
     * @param string $sSubject
     * @param int $iReaded
     * @param string $iLogin
     * @param string $sGroup
     * @param string $sBody
     * @param string $sSender
     *
     * @return boolean
     */
    function sendMail($iSender, $iReceiveTime, $sSubject, $iReaded = 0, $iLogin = '', $sGroup = '', $sBody = '', $sSender = '')
    {
        if (empty($iSender) || !is_numeric($iSender) || (empty($iLogin) && empty($sGroup)) || empty($sSubject) || empty($sBody))
        {
            trigger_error("UpDownActions::sendMail incorrect input parameters", E_USER_WARNING);
            return false;
        }
        $iReceiveTime = !empty($iReceiveTime) ? $iReceiveTime : time() + 10;

        $sQuery = "MAILSEND-MASTER={$this->password}|RECEIVE_TIME={$iReceiveTime}|SENDER={$iSender}|SENDER_DESCRIPTION={$sSender}|LOGIN={$iLogin}|SUBJECT={$sSubject}|READED={$iReaded}|GROUP={$sGroup}|BODY={$sBody}";
        $sResult = $this->makeRequest($sQuery);
        $aResult = explode("\r\n", $sResult);

        return ($aResult[0] == "OK");
    }

    function getAllGroups()
    {
        $sQuery = "GROUPSALL-MASTER={$this->password}";
        $sResult = $this->MakeRequest(array("query" => $sQuery));
        $aGroups = explode("\r\n", trim($sResult));
        array_shift($aGroups);

        return $aGroups;
    }

    function addIndexTick($sSymbol, $fBid, $fAsk)
    {
        if (empty($sSymbol) || empty($fBid) || empty($fAsk) || !is_numeric($fBid) || !is_numeric($fAsk))
        {
            trigger_error("UpDownActions::addIndexTick incorrect input parameters", E_USER_WARNING);
            return false;
        }

        $sQuery = "ADDTICK-MASTER=asdasd22|SYMBOL={$sSymbol}|BID={$fBid}|ASK={$fAsk}";
        $sResult = $this->makeRequest($sQuery);

        $iPos = strpos($sResult, "OK\r\n");

        return ($iPos === false || $iPos != 0)?false:true;
    }

    function getAccessTokens($params)
    {
        if (empty($params['account_id']) || !is_numeric($params['account_id'])) {
            trigger_error("UpDownActions::getAccessTokens incorrect input parameters", E_USER_WARNING);
            return false;
        }

        /**
         * @var $Account Accounts
         */
        $Account = Accounts::findOne($params['account_id']);
        $aForeignSettings = json_decode($Account->foreign_settings, true);
        $sURL = "api/v1/account/{$Account->foreign_account}/appkeys/";

        $aResult = $this->makeRequest($sURL, [], $aForeignSettings['access_token']['access_token']);

        return $aResult;
    }

    function getUserCapabilities($params)
    {
        if (empty($params['account_id']) || !is_numeric($params['account_id'])) {
            trigger_error("UpDownActions::getAccessTokens incorrect input parameters", E_USER_WARNING);
            return false;
        }

        /**
         * @var $Account Accounts
         */
        $Account = Accounts::findOne($params['account_id']);
        $sURL = "api/v1/capabilities/";
        $aData = [
            'apikey'    => $this->apikey,
            'username'  => "user_{$Account->user_id}_{$Account->id}@privatefx.com",
        ];

        $aResult = $this->makeRequest($sURL, $aData, '', false);

        return $aResult;
    }

    function getTransactions($params)
    {
        if (empty($params['account_id']) || !is_numeric($params['account_id'])) {
            trigger_error("UpDownActions::getAccessTokens incorrect input parameters", E_USER_WARNING);
            return false;
        }

        /**
         * @var $Account Accounts
         */
        $Account = Accounts::findOne($params['account_id']);
        $aForeignSettings = json_decode($Account->foreign_settings, true);
        $sURL = "api/v1/account/{$Account->foreign_account}/transactions/";

        $aResult = $this->makeRequest($sURL, [], $aForeignSettings['access_token']['access_token'], false);

        return $aResult;
    }

}