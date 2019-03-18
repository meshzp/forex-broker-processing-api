<?php

namespace app\models;

use Yii;
use yii\behaviors\AttributeBehavior;
use yii\db\ActiveRecord;
use yii\db\Expression;

/**
 * This is the model class for table "btc_redeem_codes".
 *
 * @property integer $id
 * @property string $address
 * @property string $redeem_code
 * @property string $invoice
 * @property integer $balance
 * @property integer $pending_balance
 * @property integer $paid_out
 * @property string $date_updated
 *
 */
class BtcRedeemCodes extends ActiveRecord
{

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'btc_redeem_codes';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['address', 'redeem_code', 'invoice'], 'required'],
            [['id', 'balance', 'pending_balance', 'paid_out'], 'integer', 'min' => 0],
        ];
    }


    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            [
                'class'      => AttributeBehavior::className(),
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => 'date_updated',
                    ActiveRecord::EVENT_BEFORE_UPDATE => 'date_updated',
                ],
                'value'      => new Expression('NOW()'),
            ],
        ];
    }


    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id'              => 'ID',
            'address'         => 'Address',
            'redeem_code'     => 'Redeem Code',
            'invoice'         => 'Invoice',
            'balance'         => 'Balance',
            'pending_balance' => 'Pending Balance',
            'paid_out'        => 'Paid Out',
        ];
    }

    public function bitapsCreateRedeemRequest()
    {
        $Bitaps  = new Bitaps();
        $respond = $Bitaps->createRedeemCheck();
        if (isset($respond['invoice'], $respond['address'], $respond['redeem_code'])) {
            $this->invoice     = $respond['invoice'];
            $this->address     = $respond['address'];
            $this->redeem_code = $respond['redeem_code'];
        } else {
            $this->addError('invoice', 'Unable to create redeem code');
        }

        return $this;
    }

    public static function getLatesRedeemCode()
    {
        return self::find()->orderBy(['id' => SORT_DESC])->one();
    }

    public function bitapsGetRedeemCodeRequest()
    {
        $Bitaps  = new Bitaps();
        $respond = $Bitaps->getRedeemCodeInfo($this->redeem_code);
        if (isset($respond['balance'], $respond['address'], $respond['pending_balance'], $respond['paid_out'])) {
            $this->balance         = $respond['balance'];
            $this->address         = $respond['address'];
            $this->pending_balance = $respond['pending_balance'];
            $this->paid_out        = $respond['paid_out'];
        } else {
            $this->addError('invoice', 'Unable to find redeem code');
        }

        return $this;
    }

}
