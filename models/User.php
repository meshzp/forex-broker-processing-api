<?php
namespace app\models;

use Yii;
use yii\base\NotSupportedException;
use yii\behaviors\AttributeBehavior;
use yii\db\ActiveRecord;
use yii\db\Expression;
use yii\web\IdentityInterface;

/**
 * User model
 *
 * @property integer $id
 * @property string $nickname
 * @property string $country
 * @property string $city
 * @property string $surname
 * @property string $surname_latin
 * @property string $name
 * @property string $name_latin
 * @property string $secname
 * @property string $birthdate
 * @property integer $document_type
 * @property string $document_name
 * @property string $document_serial
 * @property string $document_number
 * @property string $document_issuedby
 * @property string $document_receivedate
 * @property string $address
 * @property string $postal_code
 * @property string $mobile
 * @property string $mobile_confirmed
 * @property string $email
 * @property string $email_confirmed
 * @property string $password_hash
 * @property string $password_reset_token
 * @property string $auth_key
 * @property integer $auth_type
 * @property string $telephone_pass
 * @property string $date_created
 * @property string $ip_address
 * @property string $doorway
 * @property integer $status
 * @property integer $reg_duration
 * @property integer $identified
 */
class User extends ActiveRecord implements IdentityInterface
{
    const STATUS_BANNED = 0;
    const STATUS_ACTIVE = 1;

    const STATUS_UNCONFIRMED = 0;
    const STATUS_CONFIRMED = 1;

    const IDENTIFIED = 1;
    const UNIDENTIFIED = 0;

    const AUTHTYPE_SIMPLE = 1;
    const AUTHTYPE_APP = 2;
    const AUTHTYPE_SMS = 3;
    const AUTHTYPE_RECOVERY = 4;
    const AUTHTYPE_FALLBACK = 5;

    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'web.users';
    }

    /**
     * @inheritdoc
     */
    public function behaviors()
    {
        return [
            [
                'class' => AttributeBehavior::className(),
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => 'auth_type',
                ],
                'value' => self::AUTHTYPE_SIMPLE,
            ],
            [
                'class' => AttributeBehavior::className(),
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => 'date_created',
                ],
                'value' => new Expression('NOW()'),
            ],
            [
                'class' => AttributeBehavior::className(),
                'attributes' => [
                    ActiveRecord::EVENT_BEFORE_INSERT => 'status',
                ],
                'value' => self::STATUS_ACTIVE,
            ],
        ];
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            ['status', 'default', 'value' => self::STATUS_ACTIVE],
            ['status', 'in', 'range' => [self::STATUS_ACTIVE, self::STATUS_BANNED]],
            ['mobile_confirmed', 'default', 'value' => self::STATUS_UNCONFIRMED],
            ['mobile_confirmed', 'in', 'range' => [self::STATUS_UNCONFIRMED, self::STATUS_CONFIRMED]],
            ['email_confirmed', 'default', 'value' => self::STATUS_UNCONFIRMED],
            ['email_confirmed', 'in', 'range' => [self::STATUS_UNCONFIRMED, self::STATUS_CONFIRMED]],

            ['identified', 'safe'],
            ['identified', 'default', 'value' => self::UNIDENTIFIED],
            ['identified', 'in', 'range' => [self::UNIDENTIFIED, self::IDENTIFIED]],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => Yii::t('fx-users', 'ID'),
            'nickname' => Yii::t('fx-users', 'Nickname'),
            'country' => Yii::t('fx-users', 'Country'),
            'city' => Yii::t('fx-users', 'City'),
            'surname' => Yii::t('fx-users', 'Surname'),
            'surname_latin' => Yii::t('fx-users', 'Surname Latin'),
            'name' => Yii::t('fx-users', 'Name'),
            'name_latin' => Yii::t('fx-users', 'Name Latin'),
            'secname' => Yii::t('fx-users', 'Secname'),
            'birthdate' => Yii::t('fx-users', 'Birthdate'),
            'document_type' => Yii::t('fx-users', 'Document Type'),
            'document_name' => Yii::t('fx-users', 'Document Name'),
            'document_serial' => Yii::t('fx-users', 'Document Serial'),
            'document_number' => Yii::t('fx-users', 'Document Number'),
            'document_issuedby' => Yii::t('fx-users', 'Document Issuedby'),
            'document_receivedate' => Yii::t('fx-users', 'Document Receivedate'),
            'address' => Yii::t('fx-users', 'Address'),
            'postal_code' => Yii::t('fx-users', 'Postal Code'),
            'mobile' => Yii::t('fx-users', 'Mobile'),
            'mobile_confirmed' => Yii::t('fx-users', 'Mobile Confirmed'),
            'email' => Yii::t('fx-users', 'Email'),
            'email_confirmed' => Yii::t('fx-users', 'Email Confirmed'),
            'password_hash' => Yii::t('fx-users', 'Password Hash'),
            'password_reset_token' => Yii::t('fx-users', 'Password Reset Token'),
            'auth_key' => Yii::t('fx-users', 'Auth Key'),
            'auth_type' => Yii::t('fx-users', 'Auth Type'),
            'telephone_pass' => Yii::t('fx-users', 'Telephone Pass'),
            'date_created' => Yii::t('fx-users', 'Date Created'),
            'ip_address' => Yii::t('fx-users', 'Ip Address'),
            'doorway' => Yii::t('fx-users', 'Doorway'),
            'status' => Yii::t('fx-users', 'Status'),
            'reg_duration' => Yii::t('fx-users', 'Reg Duration'),
            'identified' => Yii::t('fx-users', 'Identified Status'),
        ];
    }

    public function setMobileConfirmed()
    {
        $this->mobile_confirmed = self::STATUS_CONFIRMED;
        $this->save();
    }

    public function setEmailConfirmed()
    {
        $this->email_confirmed = self::STATUS_CONFIRMED;
        $this->save();
    }

    /**
     * @inheritdoc
     */
    public static function findIdentity($id)
    {
        return static::findOne(['id' => $id, 'status' => self::STATUS_ACTIVE]);
    }

    /**
     * @inheritdoc
     */
    public static function findIdentityByAccessToken($token, $type = null)
    {
        throw new NotSupportedException('"findIdentityByAccessToken" is not implemented.');
    }

    /**
     * Finds user by nickname
     *
     * @param string $nickname
     * @return static|null
     */
    public static function findByUsername($nickname)
    {
        return static::findOne(['nickname' => $nickname, 'status' => self::STATUS_ACTIVE]);
    }

    /**
     * Finds user by password reset token
     *
     * @param string $token password reset token
     * @return static|null
     */
    public static function findByPasswordResetToken($token)
    {
        if (!static::isPasswordResetTokenValid($token)) {
            return null;
        }

        return static::findOne([
            'password_reset_token' => $token,
            'status' => self::STATUS_ACTIVE,
        ]);
    }

    /**
     * Finds out if password reset token is valid
     *
     * @param string $token password reset token
     * @return boolean
     */
    public static function isPasswordResetTokenValid($token)
    {
        if (empty($token)) {
            return false;
        }

        $timestamp = (int)substr($token, strrpos($token, '_') + 1);
        $expire = Yii::$app->params['user.passwordResetTokenExpire'];
        return $timestamp + $expire >= time();
    }

    /**
     * @inheritdoc
     */
    public function getId()
    {
        return $this->getPrimaryKey();
    }

    /**
     * @inheritdoc
     */
    public function getAuthKey()
    {
        return $this->auth_key;
    }

    /**
     * @inheritdoc
     */
    public function validateAuthKey($authKey)
    {
        return $this->getAuthKey() === $authKey;
    }

    /**
     * Validates password
     *
     * @param string $password password to validate
     * @return boolean if password provided is valid for current user
     */
    public function validatePassword($password)
    {
        return Yii::$app->security->validatePassword($password, $this->password_hash);
    }

    /**
     * Generates password hash from password and sets it to the model
     *
     * @param string $password
     */
    public function setPassword($password)
    {
        $this->password_hash = Yii::$app->security->generatePasswordHash($password);
    }

    /**
     * Generates "remember me" authentication key
     */
    public function generateAuthKey()
    {
        $this->auth_key = Yii::$app->security->generateRandomString();
    }

    /**
     * Generates new password reset token
     */
    public function generatePasswordResetToken()
    {
        $this->password_reset_token = Yii::$app->security->generateRandomString() . '_' . time();
    }

    /**
     * Removes password reset token
     */
    public function removePasswordResetToken()
    {
        $this->password_reset_token = null;
    }

}
