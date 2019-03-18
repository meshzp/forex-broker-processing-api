<?php

namespace app\controllers;

use app\models\AccountsTransactions;
use Yii;
use app\models\Accounts;
use app\models\AccountsTypes;
use app\models\BalanceFundsForm;
use app\models\PammApiEvents;
use app\models\PammApiLog;

class PammapiController extends Controller
{
    const STATUS_NEW = 0;
    const STATUS_PROCESSED = 1;

    /**
     * @var PammApiLog $_log
     */
    private $_log;

    public function actionReceiver()
    {
        $this->logRequest();

        $answerArray = [];
        $answerArray += $this->pammapiMoneyOrderUpdated();
        $answerArray += $this->pammapiAgentPaymentUpdated();

        if (empty($answerArray)) {
            $answerArray = [
                'code'              => 500,
                'error_description' => 'Unknown error',
            ];
        }

        $this->_log->answer = json_encode($answerArray);
        $this->_log->save();

        return $answerArray;
    }

    private function pammapiMoneyOrderUpdated()
    {
        /**
         * @var Accounts $account
         */

        $post = Yii::$app->request->post();

        if (isset($post['event']) && $post['event'] == 'money_order_updated' && $post['status'] == 1 && $post['operation'] > 0) {
            $answerArray = [
                'code' => 500,
            ];

            $pammapiEvent = PammApiEvents::findOne(['event' => "{$post['event']}_{$post['id']}", 'status' => self::STATUS_PROCESSED]);
            if ($pammapiEvent == null) {
                $pammapiEvent = new PammApiEvents([
                    'event'  => "{$post['event']}_{$post['id']}",
                    'data'   => json_encode($post, JSON_PRETTY_PRINT + JSON_UNESCAPED_SLASHES + JSON_UNESCAPED_UNICODE),
                    'status' => self::STATUS_PROCESSED,
                ]);

                $account = Accounts::findOne($post['account_id']);
                $index = Yii::$app->db->createCommand("SELECT for_index FROM api.pamm_investors WHERE id = {$post['investor_id']}")->queryScalar();
                if ($account != null && empty($index)) {
                    $model = new BalanceFundsForm();
                    $aData = [
                        'user_id'          => $post['user_id'],
                        'account_id'       => $account->id,
                        'transaction_type' => AccountsTransactions::TRANSACTION_TYPE_PAMM,
                        'amount'           => $post['sum'],
                        'comment'          => "Withdraw from investor {$post['investor_id']}/{$post['id']}",
                    ];
                    if ($model->load($aData, '') && $model->validate() && $model->balance()) {
                        $answerArray['code'] = 200;
                        $pammapiEvent->save();
                    } else {
                        $answerArray['code'] = 500;
                        $answerArray += $model->errors;
                    }
                } else {
                    if ($account == null) {
                        $answerArray['code'] = 404;
                        $answerArray['error_description'] = 'User account not found';
                    } else {
                        $answerArray['code'] = 402;
                        $answerArray['error_description'] = 'Index investor';
                    }
                }
            } else {
                $answerArray['code'] = 302;
                $answerArray['error_description'] = 'Found. Already processed';
            }

            return $answerArray;
        }

        return [];
    }

    private function pammapiAgentPaymentUpdated()
    {
        /**
         * @var Accounts $account
         */

        $post = Yii::$app->request->post();

        if (isset($post['event']) && $post['event'] == 'agent_payment_updated' && $post['status'] == 1) {
            $answerArray = [
                'code' => 500,
            ];

            $pammapiEvent = PammApiEvents::findOne(['event' => "{$post['event']}_{$post['id']}", 'status' => self::STATUS_PROCESSED]);
            if ($pammapiEvent == null) {
                $pammapiEvent = new PammApiEvents([
                    'event'  => "{$post['event']}_{$post['id']}",
                    'data'   => json_encode($post, JSON_PRETTY_PRINT + JSON_UNESCAPED_SLASHES + JSON_UNESCAPED_UNICODE),
                    'status' => self::STATUS_PROCESSED,
                ]);

                $account = Accounts::findOne(['user_id' => $post['partner_user_id'], 'account_type' => AccountsTypes::ACCOUNT_TYPE_PERSONAL]);
                if ($account != null) {
                    $model = new BalanceFundsForm();
                    $aData = [
                        'user_id'          => $post['partner_user_id'],
                        'account_id'       => $account->id,
                        'transaction_type' => AccountsTransactions::TRANSACTION_TYPE_PARTNER,
                        'amount'           => $post['bonus_received'],
                        'comment'          => "Agent profit bonus {$post['investor_id']}/{$post['id']}",
                    ];
                    if ($model->load($aData, '') && $model->validate() && $model->balance()) {
                        $answerArray['code'] = 200;
                        $pammapiEvent->save();
                    } else {
                        $answerArray['code'] = 500;
                        $answerArray += $model->errors;
                    }
                } else {
                    $answerArray['code'] = 404;
                    $answerArray['error_description'] = 'User account not found';
                }
            } else {
                $answerArray['code'] = 302;
                $answerArray['error_description'] = 'Found. Already processed';
            }

            return $answerArray;
        }

        return [];
    }

    private function logRequest()
    {
        if ($this->_log == null) {
            $this->_log = new PammApiLog([
                'data' => json_encode([
                    'GET'   => Yii::$app->request->get(),
                    'POST'  => Yii::$app->request->post(),
                ])
            ]);

            return $this->_log->save();
        }

        return true;
    }

}
