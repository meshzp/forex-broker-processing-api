<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "accounts_types".
 *
 * @property integer $id
 * @property string $type_name
 * @property integer $type_unique
 * @property string $driver
 * @property string $group_name
 *
 * @property Accounts[] $accounts
 */
class AccountsTypes extends ActiveRecord
{
    const ACCOUNT_TYPE_PERSONAL = 1;
    const DRIVER_WEBACTIONS = 'webactions';
    const DRIVER_DBACTIONS = 'dbactions';
    const DRIVER_UPDOWNACTIONS = 'updownactions';
    const DRIVER_BOACTIONS = 'boactions';

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'accounts_types';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['type_name', 'type_unique', 'driver', 'group_name'], 'required'],
            [['type_name', 'driver', 'group_name'], 'string', 'max' => 32],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'type_name' => 'Type Name',
            'type_unique' => 'Type Unique',
            'driver' => 'Driver',
            'group_name' => 'Group Name',
        ];
    }

    /**
     * @return \yii\db\ActiveQuery
     */
    public function getAccounts()
    {
        return $this->hasMany(Accounts::className(), ['account_type' => 'id']);
    }
}
