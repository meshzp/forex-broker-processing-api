<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "deals".
 *
 * @property integer $id
 * @property integer $account_id
 * @property integer $status
 * @property string $symbol
 * @property string $cmd
 * @property string $open_price
 * @property string $close_price
 * @property string $bet
 * @property string $profit
 * @property string $take_profit
 * @property string $stop_loss
 * @property string $open_time
 * @property string $close_time
 * @property string $balance
 * @property string $amount
 */
class BoTrades extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'bo_trades';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['account_id', 'status', 'symbol', 'cmd', 'open_price', 'close_price', 'bet', 'profit', 'open_time', 'close_time', 'balance'], 'required'],
            [['account_id', 'status'], 'integer'],
            [['open_price', 'close_price', 'bet', 'profit', 'take_profit', 'stop_loss', 'balance', 'amount'], 'number'],
            [['open_time', 'close_time'], 'safe'],
            [['symbol'], 'string', 'max' => 6],
            [['cmd'], 'string', 'max' => 5],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('fx', 'ID'),
            'account_id' => Yii::t('fx', 'Account ID'),
            'status' => Yii::t('fx', 'Status'),
            'symbol' => Yii::t('fx', 'Symbol'),
            'cmd' => Yii::t('fx', 'Cmd'),
            'open_price' => Yii::t('fx', 'Open Price'),
            'close_price' => Yii::t('fx', 'Close Price'),
            'bet' => Yii::t('fx', 'Bet'),
            'profit' => Yii::t('fx', 'Profit'),
            'take_profit' => Yii::t('fx', 'Take Profit'),
            'stop_loss' => Yii::t('fx', 'Stop Loss'),
            'open_time' => Yii::t('fx', 'Open Time'),
            'close_time' => Yii::t('fx', 'Close Time'),
            'balance' => Yii::t('fx', 'Balance'),
        ];
    }
}
