<?php
// vim: set ts=4 sw=4 sts=4 et:

/**
 * @file Provides support for TargetPay iDEAL, Mister Cash, Sofort Banking, Credit and Paysafe
*
* @author Yellow Melon B.V.
*         @url http://www.idealplugins.nl
*
*/
namespace XLite\Module\TargetPay\Payment\Base;

use XLite\Module\TargetPay\Payment\Base\TargetPayCore;
use XLite\Module\TargetPay\Payment\Model\TargetPaySale;
use XLite\Module\TargetPay\Payment\Model\AfterpayValidationException;

class TargetPayPlugin extends \XLite\Model\Payment\Base\WebBased
{

    protected $targetPayCore = null;

    protected $payMethod = null;

    protected $appId = null;

    protected $language = null;

    protected $bankId = null;

    protected $allow_nobank = false;

    protected $params = null;

    /**
     * Check if test mode is enabled
     *
     * {@inheritdoc}
     *
     * @see \XLite\Model\Payment\Base\Processor::isTestMode()
     */
    public function isTestMode(\XLite\Model\Payment\Method $method)
    {
        return $method->getSetting('mode') != 'live';
    }

    /**
     * Get payment method admin zone icon URL
     *
     * @param \XLite\Model\Payment\Method $method
     *            Payment method
     *
     * @return string
     */
    public function getAdminIconURL(\XLite\Model\Payment\Method $method)
    {
        return \XLite::getInstance()->getShopURL() . 'skins/admin/modules/TargetPay/Payment/' . $this->payMethod . '.png';
    }

    /**
     * Check payment is configured or not
     *
     * {@inheritdoc}
     *
     * @see \XLite\Model\Payment\Base\Processor::isConfigured()
     */
    public function isConfigured(\XLite\Model\Payment\Method $method)
    {
        return parent::isConfigured($method) && $method->getSetting('rtlo');
    }

    /**
     * Init the targetPayment
     *
     * @return \TargetPayCore
     */
    protected function initTargetPayment()
    {
        if ($this->targetPayCore != null) {
            return $this->targetPayCore;
        }
        $pay_amount = round(100 * $this->transaction->getCurrency()->roundValue($this->transaction->getValue()));

        $this->targetPayCore = new TargetPayCore($this->payMethod, $this->getRTLO(), $this->appId, $this->language, $this->isTestMode($this->transaction->getPaymentMethod()));
        $this->targetPayCore->setBankId($this->bankId);
        $this->targetPayCore->setAmount($pay_amount);
        $this->targetPayCore->setCancelUrl($this->getReturnURL(null, true, true));
        $this->targetPayCore->setReturnUrl($this->getReturnURL(null, true));
        $this->targetPayCore->setReportUrl($this->getCallbackURL(null, true));
        $this->targetPayCore->setDescription($this->getTransactionDescription());
        return $this->targetPayCore;
    }

    /**
     * Check if the setting is OK
     *
     * {@inheritdoc}
     *
     * @see \XLite\Model\Payment\Base\Processor::isApplicable()
     */
    public function isApplicable(\XLite\Model\Order $order, \XLite\Model\Payment\Method $method)
    {
        // Check method
        if (empty($this->payMethod) || empty($this->appId)) {
            return false;
        }
        return true;
    }

    /**
     * Get RTLO setting
     */
    protected function getRTLO()
    {
        if (! empty($this->rtlo)) {
            return $this->rtlo;
        }

        $method = $this->transaction->getPaymentMethod();
        $this->rtlo = $method->getSetting('rtlo');
        return $this->rtlo;
    }

    /**
     * get the description of payment
     */
    protected function getTransactionDescription()
    {
        return "Order #" . $this->getOrder()->order_id;
    }

    /**
     * Get the client host name
     *
     * @return unknown
     */
    protected function getClientHost()
    {
        return $_SERVER["HTTP_HOST"];
    }

    /**
     * The payment URL
     *
     * {@inheritdoc}
     *
     * @see \XLite\Model\Payment\Base\WebBased::getFormURL()
     */
    protected function getFormURL()
    {
        $this->initTargetPayment();
        // Check the payment URL
        if (! empty($this->targetPayCore->getBankUrl())) {
            return $this->validateBankUrl($this->targetPayCore->getBankUrl());
        }

        $this->targetPayCore->bindParam('email', $this->getOrder()->getProfile()->getLogin());
        $this->targetPayCore->bindParam('userip', $_SERVER["REMOTE_ADDR"]);
        if(!empty($this->params)){
            foreach ($this->params as $key => $val){
                $this->targetPayCore->bindParam($key, $val);
            }
        }
        // init transaction from Targetpay before redirect to bank
        $result = $this->targetPayCore->startPayment($this->allow_nobank);
        if ($result) {
            // Insert order to targetpay sale report
            $sale = new TargetPaySale();
            $sale->order_id = $this->getOrder()->order_id;
            $sale->method = $this->targetPayCore->getPayMethod();
            $sale->amount = $this->targetPayCore->getAmount();
            $sale->status = \XLite\Model\Payment\Transaction::STATUS_INITIALIZED;
            $sale->targetpay_txid = $this->targetPayCore->getTransactionId();
            $sale->more = $this->targetPayCore->getMoreInformation();
            $sale->targetpay_response = $this->targetPayCore->getTargetResponse();
            // $sale->paid = new \DateTime("now");
            \XLite\Core\Database::getRepo('\XLite\Module\TargetPay\Payment\Model\TargetPaySale')->insert($sale);
            // Set order status
            $order = $this->getOrder();
            $order->setShippingStatus(\XLite\Model\Order\Status\Shipping::STATUS_NEW);
            $order->setOrderNumber(\XLite\Core\Database::getRepo('XLite\Model\Order')->findNextOrderNumber());
            \XLite\Core\Database::getEM()->persist($order);
            \XLite\Core\Database::getEM()->flush();
            // Return the URL
            if(is_string($result)){
                return $this->validateBankUrl($this->targetPayCore->getBankUrl());
            }
            // For BW, AFP results
            // Redirect to return URL to check result
            $sale_return_url = $this->targetPayCore->getReturnUrl() . "&sid=" . $sale->id;
            //return $sale_return_url;
            // echo $sale_return_url; die;
            header("Location:" . $sale_return_url);
            exit(0);
        }
        // Show error message
        $error_msg = $this->targetPayCore->getErrorMessage();
        // Check for afterpay error message
        if($this->targetPayCore->getPayMethod() == "AFP"){
            // Check exception for Afterpay
            $exception = new AfterpayValidationException($this->targetPayCore->getErrorMessage());
            if ($exception->IsValidationError()) {
                $error_msg = "";
                foreach ($exception->getErrorItems() as $key => $value) {
                    $error_msg .= (is_array($value)) ? implode(", ", $value) : $value;
                    $error_msg .= "<br/>";
                }
            }
        }
        \XLite\Core\TopMessage::addError($error_msg);
        return \XLite\Core\Converter::buildURL("checkout");
    }

    /**
     * validate and remove dirty tag
     *
     * @param unknown $location
     * @return unknown
     */
    protected function validateBankUrl($location)
    {
        $location = preg_replace('/[\x00-\x1f].*$/sm', '', $location);
        $location = str_replace(array(
            '"',
            "'",
            '<',
            '>'
        ), array(
            '&quot;',
            '&#039;',
            '&lt;',
            '&gt;'
        ), $this->convertAmp($location));
        return $location;
    }

    /**
     * validate and remove dirty tag
     *
     * @param unknown $str
     * @return unknown
     */
    protected function convertAmp($str)
    {
        // Do not convert html entities like &thetasym; &Omicron; &euro; &#8364; &#8218;
        return preg_replace('/&(?![a-zA-Z0-9#]{1,8};)/Ss', '&amp;', $str);
    }

    /**
     * Don't pass parame to form
     *
     * {@inheritdoc}
     *
     * @see \XLite\Model\Payment\Base\WebBased::getFormFields()
     */
    protected function getFormFields()
    {
        return [
            'paymethod' => $this->payMethod
        ];
    }

    /**
     * Process return data
     *
     * @param \XLite\Model\Payment\Transaction $transaction
     * @param unknown $is_callback
     */
    protected function handlePaymentResult(\XLite\Model\Payment\Transaction $transaction, $is_callback = false)
    {
        $request = \XLite\Core\Request::getInstance();
        // Check if callback method is not POST
        if ($is_callback && $request->isGet()) {
            \XLite\Core\TopMessage::addError('The callback method must be POST');
            return;
        }
        // Check if user cancel the transaction
        if ($request->cancel) {
            $this->setDetail('status', 'Customer has canceled checkout before completing their payments', 'Status');
            $transaction->setNote('Customer has canceled checkout before completing their payments');
            // Update transaction status
            $transaction->setStatus($transaction::STATUS_CANCELED);
            if (!empty($request->trxid)) {
                $sale = (new \XLite\Module\TargetPay\Payment\Model\TargetPaySale())->findByTargetPayId($request->trxid);
                if ($sale != null){
                    $sale->status = $transaction::STATUS_CANCELED;
                    \XLite\Core\Database::getRepo('\XLite\Module\TargetPay\Payment\Model\TargetPaySale')->update($sale);
                    // Commit the transaction
                    \XLite\Core\Database::getEM()->flush();
                }
            }
        } else {
            if (! empty($request->trxid)) {
                // Check the local transaction
                $sale = (new \XLite\Module\TargetPay\Payment\Model\TargetPaySale())->findByTargetPayId($request->trxid);
                if ($sale == null || empty($sale)) {
                    \XLite\Core\TopMessage::addError('No entry found with TargetPay id: ' . htmlspecialchars($request->trxid));
                    return;
                }
                // Check current status of transaction
                if($transaction->getStatus() === $transaction::STATUS_SUCCESS){
                    \XLite\Core\TopMessage::addInfo('Your transaction had been processed!');
                    return;
                }
                // Check payment with Targetpay
                $this->initTargetPayment();
                $paid = $this->targetPayCore->checkPayment($request->trxid);
                if ($paid) {
                    $status = $transaction::STATUS_SUCCESS;
                    // Update local as paid
                    $sale->paid = new \DateTime("now");
                } elseif ($is_callback) {
                    $status = $transaction::STATUS_INPROGRESS;
                } else {
                    $status = $transaction::STATUS_PENDING;
                    $this->markCallbackRequestAsInvalid($this->targetPayCore->getErrorMessage());
                }
                // Update targetpay sale report
                $sale->status = $status;
                \XLite\Core\Database::getRepo('\XLite\Module\TargetPay\Payment\Model\TargetPaySale')->update($sale);
                // Commit the transaction
                \XLite\Core\Database::getEM()->flush();
                // Update the order status and change to Order in cases of reportUrl is called.
                $transaction->setStatus($status);
                // Commit the transaction
                \XLite\Core\Database::getEM()->flush();
                // Update the order if transaction success
                if ($paid) {
                    // Update to mark transaction as Order
                    $this->getOrder()->markAsOrder();
                    // Update order tatus
                    $this->getOrder()->setPaymentStatusByTransaction($transaction);
                    $this->getOrder()->setPaymentStatus($status);
                }
                // Commit the transaction
                \XLite\Core\Database::getEM()->flush();
            }
        }
    }
}
