<?php

namespace app\commands;

use app\models\RecalcBalanceForm;
use Yii;
use yii\console\Controller;
use yii\db\Transaction;

class AccountsController extends Controller
{

    /**
     * @return bool
     * @throws \yii\db\Exception
     */
    public function actionUpdateBalances()
    {
        $transaction = Yii::$app->db->beginTransaction(Transaction::READ_COMMITTED);
        try {
            Yii::$app->db->createCommand('UPDATE accounts AS a, api.MT4_USERS AS mtu SET a.balance = GREATEST(mtu.BALANCE, 0) WHERE mtu.LOGIN = a.foreign_account AND a.account_type IN (5, 10, 11, 12, 13, 16, 17, 20, 34, 35, 36)')->execute();
            echo "Balances updated successfully" . PHP_EOL;
            $transaction->commit();
        } catch (\Exception $e) {
            echo "ERROR" . PHP_EOL;
            $transaction->rollBack();
            return false;
        }

        return true;
    }

    public function actionUpdateBoBalances()
    {
        $GetAccountIdAll = Yii::$app->db->createCommand("SELECT a.id, a.foreign_account FROM accounts AS a WHERE a.account_type IN (29, 30) AND a.foreign_account > 0 GROUP BY a.id ORDER BY a.id")->queryAll();

        $transaction = Yii::$app->db->beginTransaction(Transaction::READ_COMMITTED);
        try {
            $total = count($GetAccountIdAll);
            $current = 0;
            $queries = [];
            foreach ($GetAccountIdAll as $currentAccount) {
                $current++;
                if (!is_numeric($currentAccount['foreign_account'])) {
                    echo "Getting Margin Info ({$current}/{$total}) for account #{$currentAccount['id']} foreign_account doesn't exist..." . PHP_EOL;
                    continue;
                }
                echo "Getting Margin Info ({$current}/{$total}) for account #{$currentAccount['id']} foreign_account #{$currentAccount['foreign_account']}... ";
                $aResult = Yii::$app->boactions->refreshBalance($currentAccount['foreign_account']);

                if (is_array($aResult) && isset($aResult['status']) && $aResult['status'] == true) {
                    $queries[] = "UPDATE accounts SET balance = {$aResult['balance']} WHERE id = {$currentAccount['id']}";
                    echo $aResult['balance'] . "... " . PHP_EOL;

                } else {
                    echo "ERROR" . PHP_EOL;
                }
            }
            foreach ($queries as $query) {
                Yii::$app->db->createCommand($query)->execute();
            }
            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollBack();
            return false;
        }

        return true;
    }

    public function actionUpdateUpdownBalances()
    {
        $GetAccountIdAll = Yii::$app->db->createCommand("SELECT a.id FROM accounts AS a INNER JOIN accounts_transactions AS at ON at.account_id = a.id WHERE a.account_type IN (21, 25) GROUP BY a.id ORDER BY a.id")->queryColumn();
        $transaction = Yii::$app->db->beginTransaction(Transaction::READ_COMMITTED);
        try {
            $total = count($GetAccountIdAll);
            $current = 0;
            $queries = [];
            foreach ($GetAccountIdAll as $currentAccountId) {
                $current++;
                echo "Getting Margin Info ({$current}/{$total}) for account #{$currentAccountId}... ";
                $aParams = [
                    'account_id'    => $currentAccountId,
                ];
                $aResult = Yii::$app->updownactions->getUserCapabilities($aParams);
                echo (($aResult['demo'])?"demo":"real") . "... ";
                //$aParams['demo'] = $aResult['demo'];

                $aResult = Yii::$app->updownactions->getTradesMarginInfo($aParams);
                if (is_array($aResult)) {
                    $queries[] = "UPDATE accounts SET balance = {$aResult['equity']} WHERE id = {$currentAccountId}";
                    echo $aResult['equity'] . "... ";

                    $aResult = Yii::$app->updownactions->getTransactions($aParams);
                    echo count($aResult) . " transactions." . PHP_EOL;
                    if (is_array($aResult)) {
                        foreach ($aResult as $aTransaction) {
                            $sInfo = json_encode($aTransaction['info']);
                            Yii::$app->db->createCommand("INSERT INTO updown_transactions VALUES ({$aTransaction['id']}, {$currentAccountId}, '{$aTransaction['type']}', DATE_ADD('{$aTransaction['time']}', INTERVAL 180*60 SECOND), '{$aTransaction['asset']}', '{$sInfo}', {$aTransaction['sum']}, '{$aTransaction['status']}') ON DUPLICATE KEY UPDATE `account_id` = VALUES(`account_id`), `type` = VALUES(`type`), `time` = VALUES(`time`), `asset` = VALUES(`asset`), `info` = VALUES(`info`), `sum` = VALUES(`sum`), `status` = VALUES(`status`)")->execute();
                        }
                    }
                } else {
                    echo "ERROR" . PHP_EOL;
                }
            }
            foreach ($queries as $query) {
                Yii::$app->db->createCommand($query)->execute();
            }
            $transaction->commit();
        } catch (\Exception $e) {
            $transaction->rollBack();
            return false;
        }

        return true;
    }

    /**
     * @param int $user_id
     * @param int $account_id
     *
     * @return bool
     */
    public function actionRecalcBalance($user_id, $account_id)
    {
        $recalcBalanceForm = new RecalcBalanceForm([
            'account_id' => $account_id,
            'user_id' => $user_id,
        ]);
        $result = $recalcBalanceForm->recalcBalance();
        echo "Updating balance of account#{$account_id} for user #{$user_id}" . PHP_EOL;

        return $result;
    }
}
