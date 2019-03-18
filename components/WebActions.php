<?php

namespace app\components;

use Yii;
use yii\base\Component;
use app\models\Accounts;

class WebActions extends Component
{
    public $host;
    public $port;
    public $password;

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
     * @param   mixed   $mQuery
     * @param   string  $sServerIP
     * @return  mixed
     */
    private function makeRequest($mQuery, $sServerIP = '')
    {
        if (empty($sServerIP))
        {
            $sServerIP = $this->host;
        }

        $aFields = array(
            'query'		=> (is_array($mQuery))?serialize($mQuery):mb_convert_encoding($mQuery, "UTF-8", "Windows-1251"),
            'status'	=> 0,
            'response'	=> '',
        );

        $sReturn = "ERROR\r\nИзвините, функция временно недоступна в связи с техническими работами";
        $aReturn = array();
        $aResponse = array();

        $this->connect();

        if ($this->rFAPI[$sServerIP])
        {
            if (is_array($mQuery))
            {
                foreach ($mQuery as $sQuery)
                {
                    if (fputs($this->rFAPI[$sServerIP], "$sQuery\n") != false)
                    {
                        $sReturn = "";
                        while (!feof($this->rFAPI[$sServerIP]))
                        {
                            $sLine = fgets($this->rFAPI[$sServerIP], 1024);
                            $sLine = str_replace("\0", "", $sLine);
                            if ($sLine=="end\r\n") break;
                            $sReturn .= $sLine;
                        }
                        $aReturn[] = $sReturn;
                        $aResponse[] = mb_convert_encoding($sReturn, "UTF-8", "Windows-1251");
                    }
                }
            }
            else
            {
                if (fputs($this->rFAPI[$sServerIP], "$mQuery\n") != false)
                {
                    $sReturn = "";
                    while (!feof($this->rFAPI[$sServerIP]))
                    {
                        $sLine = fgets($this->rFAPI[$sServerIP], 1024);
                        $sLine = str_replace("\0", "", $sLine);
                        if ($sLine=="end\r\n") break;
                        $sReturn .= $sLine;
                    }
                    $aResponse[] = mb_convert_encoding($sReturn, "UTF-8", "Windows-1251");
                }
            }
            $aFields['status']		= 1;
            $aFields['response']	= serialize($aResponse);
        }

        //if (strpos($mQuery, "BALANCE-") > 0)
        //{
            Yii::$app->db->createCommand()->insert('accounts_webactions_log', $aFields)->execute();
        //}

        return (is_array($mQuery))?$aReturn:$sReturn;
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
        if (empty($params['login']) || !is_numeric($params['login']) || (empty($params['amount']) && empty($params['force'])))
        {
            trigger_error("WebActions::makePayment incorrect input parameters", E_USER_WARNING);
            return false;
        }

        $iForce = intval(!empty($params['force']));
        $iUpdateUser = intval(!empty($params['update_user']));

        $sQuery = "CLIENTSCHANGEBALANCE-MASTER={$this->password}|LOGIN={$params['login']}|VALUE={$params['amount']}|COMMENT={$params['comment']}|FORCE={$iForce}|UPDATEUSER={$iUpdateUser}";
        $sResult = $this->makeRequest($sQuery);

        $iPos = strpos($sResult, "OK\r\n");

        return ($iPos === false || $iPos != 0)?false:true;
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
            trigger_error("WebActions::makeTransfer incorrect input parameters", E_USER_WARNING);
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
     * @return bool|int
     */
    function createAccount($params = [])
    {
        if (empty($params['group']) || empty($params['name']) || empty($params['password']) || empty($params['investor'])
         || empty($params['email']) || empty($params['country']) || empty($params['city']) || empty($params['address'])
         || empty($params['phone_password']) || empty($params['zip_code']) || empty($params['user_id'])
         || empty($params['ip']) || empty($params['leverage']))
        {
            var_dump($params);
            trigger_error("WebActions::createAccount incorrect input parameters", E_USER_WARNING);
            return false;
        }

        $params['name'] = mb_convert_encoding($params['name'], "windows-1251", "UTF-8");
        $params['country'] = mb_convert_encoding($params['country'], "windows-1251", "UTF-8");
        $params['city'] = mb_convert_encoding($params['city'], "windows-1251", "UTF-8");
        $params['address'] = mb_convert_encoding($params['address'], "windows-1251", "UTF-8");
        $params['comment'] = mb_convert_encoding($params['comment'], "windows-1251", "UTF-8");
        $params['phone_password'] = mb_convert_encoding($params['phone_password'], "windows-1251", "UTF-8");

        $sQuery = "CLIENTSADDUSER-MASTER={$this->password}|IP={$params['ip']}|GROUP={$params['group']}|NAME={$params['name']}|"
                . "PASSWORD={$params['password']}|INVESTOR={$params['investor']}|EMAIL={$params['email']}|COUNTRY={$params['country']}|"
                . "STATE=|CITY={$params['city']}|ADDRESS={$params['address']}|COMMENT={$params['comment']}|PHONE={$params['phone']}|"
                . "PHONE_PASSWORD={$params['phone_password']}|STATUS=RE|ZIPCODE={$params['zip_code']}|ID={$params['user_id']}|"
                . "LEVERAGE={$params['leverage']}|AGENT={$params['agent']}|SEND_REPORTS=1|DEPOSIT=0|ENABLE=1";

        $sResult = $this->makeRequest($sQuery);
        $aResult = explode("\r\n", $sResult);

        if ($aResult[0]=="OK") {
            $mtLogin = explode("=", $aResult[1]);
            return [
                'account'   => $mtLogin[1],
                'settings'  => '',
            ];
        }

        return false;
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
            trigger_error("WebActions::getUserInfo incorrect input parameters", E_USER_WARNING);
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
            trigger_error("WebActions::checkPass incorrect input parameters", E_USER_WARNING);
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
            trigger_error("WebActions::changePass incorrect input parameters", E_USER_WARNING);
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
            trigger_error("WebActions::updateUser incorrect input parameters", E_USER_WARNING);
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
            trigger_error("WebActions::getTradesMarginInfo incorrect input parameters", E_USER_WARNING);
            return false;
        }

        /**
         * @var $account Accounts
         */
        $account = Accounts::findOne($params['account_id']);
        if ($account == null) {
            trigger_error("WebActions::getTradesMarginInfo account not found", E_USER_WARNING);
            return false;
        }
        if (empty($account->foreign_account)) {
            trigger_error("WebActions::getTradesMarginInfo foreign account not found", E_USER_WARNING);
            return false;
        }

        $sQuery = "TRADESMARGININFO-MASTER={$this->password}|LOGIN={$account->foreign_account}";
        $sResult = $this->makeRequest($sQuery);
        $aResult = explode("\r\n", $sResult);
        if ($aResult[0]=="OK")
        {
            $aTradesMarginInfo = array(
                'margin'    => $aResult[1],
                'free'      => $aResult[2],
                'equity'    => $aResult[3],
            );

            return $aTradesMarginInfo;
        }

        return false;
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
            trigger_error("WebActions::getOpenedTrades incorrect input parameters", E_USER_WARNING);
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
            trigger_error("WebActions::getOpenedTradesCount incorrect input parameters", E_USER_WARNING);
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
            trigger_error("WebActions::getClosedTrades incorrect input parameters", E_USER_WARNING);
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
            trigger_error("WebActions::getClosedTrades incorrect input parameters", E_USER_WARNING);
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
            trigger_error("WebActions::closeOrders incorrect input parameters", E_USER_WARNING);
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
            trigger_error("WebActions::getDetailedStatement incorrect input parameters", E_USER_WARNING);
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
            trigger_error("WebActions::sendMail incorrect input parameters", E_USER_WARNING);
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
            trigger_error("WebActions::addIndexTick incorrect input parameters", E_USER_WARNING);
            return false;
        }

        $sQuery = "ADDTICK-MASTER=asdasd22|SYMBOL={$sSymbol}|BID={$fBid}|ASK={$fAsk}";
        $sResult = $this->makeRequest($sQuery);

        $iPos = strpos($sResult, "OK\r\n");

        return ($iPos === false || $iPos != 0)?false:true;
    }

    /**
     * Крон по обработке отложенных запросов
     *
     * @return bool
     */
    function runQueriesCron()
    {
        ignore_user_abort(true);
        set_time_limit(0);
        ob_implicit_flush();

        $rQuery = $this->db->select('pid')->from('pamm_idx_processes')->where('process_name', 'wa_queries_dispatcher')->get();
        $iPid = ($rQuery->num_rows() > 0)?$rQuery->row()->pid:0;
        if (!empty($iPid))
        {
            $iPid = intval($iPid);
            if (posix_kill($iPid, 0))
            {
                exit("WebActionsQueriesCron: WebActionsQueriesCron is already running");
            }
        }

        $iPid = posix_getpid();
        $this->db->query("INSERT INTO pamm_idx_processes (process_name, pid) VALUES ('wa_queries_dispatcher', {$iPid}) ON DUPLICATE KEY UPDATE pid = {$iPid}");

        while (1)
        {
            $this->db
                ->select('*')
                ->from('fapi_queries_dispatcher')
                ->where('status', 0)
                ->order_by('id', 'ASC')
                ->limit(2000);
            $rQuery = $this->db->get();
            if ($rQuery->num_rows() > 0)
            {
                $aRows = $rQuery->result_array();
                foreach ($aRows as $aRow)
                {
                    $sResult = $this->makeRequest($aRow['query'], $aRow['server_ip']);
                    $iPos = strpos($sResult, "OK\r\n");

                    if ($iPos === 0)
                    {
                        $this->db->query("UPDATE fapi_queries_dispatcher SET status = 1, process_date = NOW() WHERE id = {$aRow['id']}");
                    }

                    $rQuery = $this->db->select('pid')->from('pamm_idx_processes')->where('process_name', 'wa_queries_dispatcher')->get();
                    $iPid = ($rQuery->num_rows() > 0)?$rQuery->row()->pid:0;
                    if ($iPid != posix_getpid())
                    {
                        // Уходим из процессов
                        $this->db->where('process_name', 'wa_queries_dispatcher');
                        $this->db->delete('pamm_idx_processes');
                        exit;
                    }
                }
            }

            $rQuery = $this->db->select('pid')->from('pamm_idx_processes')->where('process_name', 'wa_queries_dispatcher')->get();
            $iPid = ($rQuery->num_rows() > 0)?$rQuery->row()->pid:0;
            if ($iPid != posix_getpid())
            {
                // Уходим из процессов
                $this->db->where('process_name', 'wa_queries_dispatcher');
                $this->db->delete('pamm_idx_processes');
                exit;
            }
            sleep(7);
        }

        return true;
    }

}