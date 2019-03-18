<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;
use yii\helpers\ArrayHelper;

/**
 * This is the model class for table "processing.accounts_leverages".
 *
 * @property integer $id
 * @property integer $account_type_id
 * @property integer $leverage
 */
class AccountsLeverages extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'processing.accounts_leverages';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['account_type_id', 'leverage'], 'integer'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('fx', 'ID'),
            'account_type_id' => Yii::t('fx', 'Account Type ID'),
            'leverage' => Yii::t('fx', 'Leverage'),
        ];
    }

    public static function getLeverages($account_id) {
        $account = Accounts::findOne(['id' => $account_id]);
        if($account) {
            return self::find()->select('leverage')->where(['account_type_id' => $account->account_type])->column();
        }
        else{
            return false;
        }
    }
}
