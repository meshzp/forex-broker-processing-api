<?php

namespace app\controllers;

use app\models\BalanceFundsForm;
use app\models\Bitaps;
use app\models\BtcRedeemCodes;
use app\models\ChangeAccountParamsForm;
use app\models\ChangePasswordForm;
use app\models\ChangeTransactionDataForm;
use app\models\CreateAccountForm;
use app\models\MakePayoutForm;
use app\models\RecalcBalanceForm;
use app\models\TransferFundsForm;
use app\models\UnlockTransactionForm;
use app\models\UsersRequisites;
use Yii;

class AccountController extends Controller
{
    public function actionCreate()
    {
        $status = $accountId = false;
        $model = new CreateAccountForm();
        if ($model->load(Yii::$app->request->post(), '') && $model->validate()) {
            $accountId = $model->create();
            if ($accountId) {
                $status = true;
            }
        }

        return [
            'status' => $status,
            'data'   => [
                'account_id' => $accountId,
            ],
            'errors' => $model->errors,
        ];
    }

    public function actionBalanceFunds()
    {
        $status = false;
        $data = [];
        $model = new BalanceFundsForm();
        if ($model->load(Yii::$app->request->post(), '') && $model->validate() && $model->balance()) {
            $status = true;
            $data['transaction_id'] = $model->id;
        }

        return [
            'status' => $status,
            'data'   => $data,
            'errors' => $model->errors,
        ];
    }

    public function actionTransferFunds()
    {
        $status = false;
        $data = [];
        $model = new TransferFundsForm();
        if ($model->load(Yii::$app->request->post(), '') && $model->validate()) {
            if ($data = $model->transfer()) {
                $status = true;
            }
        }

        return [
            'status' => $status,
            'data'   => $data,
            'errors' => $model->errors,
        ];
    }

    public function actionUnlockTransaction()
    {
        $status = false;
        $model = new UnlockTransactionForm();
        if ($model->load(Yii::$app->request->post(), '') && $model->validate() && $model->unlock()) {
            $status = true;
        }

        return [
            'status' => $status,
            'data'   => [],
            'errors' => $model->errors,
        ];
    }

    public function actionChangePassword()
    {
        $status = false;
        $model = new ChangePasswordForm();
        if ($model->load(Yii::$app->request->post(), '') && $model->validate() && $model->changePassword()) {
            $status = true;
        }

        return [
            'status' => $status,
            'data'   => [],
            'errors' => $model->errors,
        ];
    }

    public function actionChangeParams() {
        $status = false;
        $model = new ChangeAccountParamsForm();
        if ($model->load(Yii::$app->request->post(), '') && $model->validate() && $model->changeParams()) {
            $status = true;
        }

        return [
            'status' => $status,
            'data'   => [],
            'errors' => $model->errors,
        ];
    }

    public function actionChangeTransactionData()
    {
        $status = false;
        $model = new ChangeTransactionDataForm();
        if ($model->load(Yii::$app->request->post(), '') && $model->validate() && $model->changeTransactionData()) {
            $status = true;
        }

        return [
            'status' => $status,
            'data'   => [],
            'errors' => $model->errors,
        ];
    }

    public function actionMakePayout()
    {
        $status = false;
        $model = new MakePayoutForm();
        if ($model->load(Yii::$app->request->post(), '') && $model->validate() && $model->makePayout()) {
            $status = true;
        }

        return [
            'status' => $status,
            'data'   => [],
            'errors' => $model->errors,
        ];
    }

    public function actionRecalcBalance()
    {
        $status = false;
        $model = new RecalcBalanceForm();
        if ($model->load(Yii::$app->request->post(), '') && $model->validate() && $model->recalcBalance()) {
            $status = true;
        }

        return [
            'status' => $status,
            'data'   => [],
            'errors' => $model->errors,
        ];
    }

    public function actionAttachRequisite()
    {
        $status = false;
        $model = new UsersRequisites(['scenario' => UsersRequisites::SCENARIO_ATTACH_REQUISITES]);
        if ($model->load(Yii::$app->request->post(), '') && $model->validate() && $model->attachRequisites()) {
            $status = true;
        }
        return [
            'status' => $status,
            'data'   => [],
            'errors' => $model->errors,
        ];
    }

    public function actionDetachRequisite()
    {
        $status = false;
        $model = new UsersRequisites(['scenario' => UsersRequisites::SCENARIO_DETACH_REQUISITES]);
        if ($model->load(Yii::$app->request->post(), '') && $model->validate() && $model->detachRequisites()) {
            $status = true;
        }
        return [
            'status' => $status,
            'data'   => [],
            'errors' => $model->errors,
        ];
    }

    public function actionCreateRedeemCode()
    {
        $status = false;
        $model = new BtcRedeemCodes();
        $model->bitapsCreateRedeemRequest();
        if ($model->save()) {
            $status = true;
        }
        return [
            'status' => $status,
            'data'   => [],
            'errors' => $model->errors,
        ];
    }

    public function actionGetRedeemCode()
    {
        $status = false;
        $model = BtcRedeemCodes::getLatesRedeemCode();
        $model->bitapsGetRedeemCodeRequest();
        if ($model->save()) {
            $status = true;
        }

        return [
            'status' => $status,
            'data'   => [
                'address'         => $model->address,
                'balance'         => $model->balance,
                'pending_balance' => $model->pending_balance,
                'paid_out'        => $model->paid_out,
            ],
            'errors' => $model->errors,
        ];
    }

    public function actionGetAddressInfo()
    {
        $status = false;
        $params = Yii::$app->request->post();
        $data   = [];
        if (isset($params['address'])) {
            $model = new Bitaps();
            $data  = $model->getAddressInfo($params['address']);
            $status = $data ? true : false;
        }

        return [
            'status' => $status,
            'data'   => $data,
            'errors' => '',
        ];
    }
}
