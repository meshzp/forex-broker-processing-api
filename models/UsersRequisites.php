<?php

namespace app\models;

use Yii;
use yii\db\ActiveRecord;

/**
 * This is the model class for table "users_requisites".
 *
 * @property integer $user_id
 * @property string $requisites
 * @property integer $owner
 */
class UsersRequisites extends ActiveRecord
{

    const OWNER = 1;
    const NOT_OWNER = 0;

    const SCENARIO_ATTACH_REQUISITES = '_attach_requisites';
    const SCENARIO_DETACH_REQUISITES = '_detach_requisites';


    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'users_requisites';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            [['user_id', 'owner'], 'integer'],
            ['owner', 'in', 'range' => [self::OWNER, self::NOT_OWNER]],
            [['requisites'], 'string', 'max' => 100],
            ['user_id', 'validateIfOwner', 'on' => [self::SCENARIO_DETACH_REQUISITES]],
            ['user_id', 'validateIfNoOwners', 'on' => [self::SCENARIO_ATTACH_REQUISITES]],
            ['requisites', 'validateIfIsset', 'on' => [self::SCENARIO_ATTACH_REQUISITES, self::SCENARIO_DETACH_REQUISITES]],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'user_id' => 'User ID',
            'requisites' => 'Requisites',
            'owner' => 'Owner',
        ];
    }

    /**
     * @param $attribute
     */
    public function validateIfIsset($attribute)
    {
        if(!self::checkIfRequisitesExist($this->requisites)) {
            $this->addError($attribute, 'requisite does not exist');
        }
    }

    /**
     * @param $attribute
     */
    public function validateIfOwner($attribute)
    {
        if(!self::checkIfOwner($this->requisites, $this->user_id)) {
            $this->addError($attribute, 'the user is not the owner of this requisite');
        }
    }

    /**
     * @param $attribute
     */
    public function validateIfNoOwners($attribute)
    {
        if(self::checkIfIssetOwners($this->requisites)) {
            $this->addError($attribute, 'this requisite belongs to another user');
        }
    }


    /**
     * Проверяет были ли пополненя по этим реквизитам. Возращает true - если были.
     * @param $requisites
     * @param null $user_id
     * @return bool
     */
    public static function checkIfRequisitesExist($requisites, $user_id = null) {

        $query = self::find()->where(['requisites' => $requisites]);
        if($user_id) {
            $query->andWhere(['user_id' => $user_id]);
        }
        $result = $query->one();
        if($result) {
            return true;
        }
        else {
            return false;
        }
    }

    public static function checkIfOwner($requisites, $user_id) {
        return self::find()->where(['requisites' => $requisites, 'user_id' => $user_id, 'owner' => self::OWNER])->exists();
    }

    public static function checkIfIssetOwners($requisites) {
        return self::find()->where(['requisites' => $requisites, 'owner' => self::OWNER])->exists();
    }

    /**
     * Назначает пользователя владельцем реквизита
     */
    public function attachRequisites()
    {
        $result = false;
        //если существует реквизит и нет владельцев
        if (self::checkIfRequisitesExist($this->requisites) && !self::checkIfIssetOwners($this->requisites)) {
            $model = self::findOne(['user_id' => $this->user_id, 'requisites' => $this->requisites]);
            if ($model) {
                $model->owner = self::OWNER;
                $model->save();
                $result = true;
            }
        }
        return $result;
    }

    /**
     * Открепляет реквизит от пользователя
     */
    public function detachRequisites()
    {
        $result = false;
        //если существует реквизит и пользователь владелец
        if (self::checkIfRequisitesExist($this->requisites) && self::checkIfOwner($this->requisites, $this->user_id)) {
            $model = self::findOne(['user_id' => $this->user_id, 'requisites' => $this->requisites]);
            if ($model) {
                $model->owner = self::NOT_OWNER;
                $model->save();
                $result = true;
            }
        }
        return $result;
    }

}