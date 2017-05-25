<?php
// vim: set ts=4 sw=4 sts=4 et:

/**
 * @file Provides support for TargetPay iDEAL, Mister Cash, Sofort Banking, Credit and Paysafe
*
* @author Yellow Melon B.V.
*         @url http://www.idealplugins.nl
*/
namespace XLite\Module\TargetPay\Payment\Processor;

use XLite\Module\TargetPay\Payment\Base\TargetPayPlugin;

class Bankwire extends TargetPayPlugin
{
    /**
     * The confirmation message after returning from Target page
     */
    private $confirmation_message = "";
    /**
     * The contructor
     */
    public function __construct()
    {
        $this->payMethod = "BW";
        $this->appId = "382a92214fcbe76a32e22a30e1e9dd9f";
        $this->currency = "EUR";
        $this->language = 'nl';
        $this->allow_nobank = true;
        // Params to add to Url
        $this->params = ['salt' => 'e381277'];
    }

    /**
     * The setting widget
     *
     * {@inheritdoc}
     *
     * @see \XLite\Model\Payment\Base\Processor::getSettingsWidget()
     */
    public function getSettingsWidget()
    {
        return 'modules/TargetPay/Payment/Bankwire.twig';
    }

    /**
     * Get payment method row checkout template
     *
     * @param \XLite\Model\Payment\Method $method Payment method
     *
     * @return string
     */
    public function getCheckoutTemplate(\XLite\Model\Payment\Method $method)
    {
        return 'modules/TargetPay/Payment/checkout/Bankwire.twig';
    }
    /**
     * return transaction process
     *
     * {@inheritdoc}
     *
     * @see \XLite\Model\Payment\Base\Online::processReturn()
     */
    public function processReturn(\XLite\Model\Payment\Transaction $transaction)
    {
        // http://xcart.local/cart.php?target=payment_return&txn_id_name=txnId&txnId=000017-UPFR&sid=9
        parent::processReturn($transaction);
        $this->processPayment($transaction, false);
    }

    /**
     * Callback message
     *
     * {@inheritdoc}
     *
     * @see \XLite\Model\Payment\Base\Online::processCallback()
     */
    public function processCallback(\XLite\Model\Payment\Transaction $transaction)
    {
        parent::processCallback($transaction);
        $this->processPayment($transaction, true);
    }

    /**
     * Process to redirect to other controller
     * {@inheritDoc}
     * @see \XLite\Model\Payment\Base\WebBased::doCustomReturnRedirect()
     */
    public function doCustomReturnRedirect()
    {
        $request = \XLite\Core\Request::getInstance();
        if(!empty($this->confirmation_message)){
            if(!empty($request->show_warning)){
                \XLite\Core\TopMessage::addRawWarning($this->confirmation_message);
            }else{
                echo $this->printConfirmInformation();
            }
        } else {
            $url = \XLite\Core\Converter::buildURL(
                'checkoutSuccess',
                '',
                array(
                    'order_number'  => $this->getOrder()->getOrderNumber(),
                    'payment' => 'bankwire'
                )
                );
            header("Location:" . $url);
            exit(0);
        }
    }
    /**
     * use custom to redirect to another pay
     * {@inheritDoc}
     * @see \XLite\Model\Payment\Base\Online::getReturnType()
     */
    public function getReturnType()
    {
        $request = \XLite\Core\Request::getInstance();
        if(!empty($request->sid)){
            $sale = (new \XLite\Module\TargetPay\Payment\Model\TargetPaySale())->findById($request->sid);
            if(!empty($sale)){
                return \XLite\Model\Payment\Base\WebBased::RETURN_TYPE_CUSTOM;
            }
        }
        return null;
    }

    /**
     * Process callback payment
     *
     * @param \XLite\Model\Payment\Transaction $transaction
     * @param unknown $callback
     */
    private function processPayment($transaction, $callback = false)
    {
        $request = \XLite\Core\Request::getInstance();
        //var_dump($request); die;
        // Check post method for callback
        if ($callback && $request->isGet()) {
            \XLite\Core\TopMessage::addError('The callback method must be POST');
            return;
        }
        if ($request->cancel){
            $this->setDetail('status', 'Customer has canceled checkout before completing their payments', 'Status');
            $transaction->setNote('Customer has canceled checkout before completing their payments');
            // Update transaction status
            $transaction->setStatus($transaction::STATUS_CANCELED);
            $this->getOrder()->setPaymentStatus($transaction::STATUS_CANCELED);
            if (!empty($request->trxid)) {
                $sale = (new \XLite\Module\TargetPay\Payment\Model\TargetPaySale())->findByTargetPayId($request->trxid);
                if ($sale != null){
                    $sale->status = $transaction::STATUS_CANCELED;
                    \XLite\Core\Database::getRepo('\XLite\Module\TargetPay\Payment\Model\TargetPaySale')->update($sale);
                    // Commit the transaction
                    \XLite\Core\Database::getEM()->flush();
                }
            }
            return;
        }
        // Process order status
        // Return from shop
        if(!empty($request->sid)){
            $sale = (new \XLite\Module\TargetPay\Payment\Model\TargetPaySale())->findById($request->sid);
            // Return URL
            if(!empty($sale))
            {
                if(!empty($sale->paid)){
                    $url = \XLite\Core\Converter::buildURL(
                        'checkoutSuccess',
                        '',
                        array(
                            'order_number'  => $this->getOrder()->getOrderNumber(),
                            'payment' => 'bankwire'
                        )
                        );
                    header("Location:" . $url);
                    exit(0);
                } elseif (!empty($sale->more)){
                    list($trxid, $accountNumber, $iban, $bic, $beneficiary, $bank) = explode("|", $sale->more);
                    $sale->targetpay_txid = $trxid;
                    $sale->status = $transaction::STATUS_INPROGRESS;
                    \XLite\Core\Database::getRepo('\XLite\Module\TargetPay\Payment\Model\TargetPaySale')->update($sale);
                    $message = $this->getResultMessage($sale);
                    // Show message to inform user
                    //\XLite\Core\TopMessage::addRawWarning($message);
                    $this->confirmation_message = $message;
                }
                // For Bankwire, making transaction as success
                $transaction->setStatus($transaction::STATUS_SUCCESS);
                // Commit the transaction
                \XLite\Core\Database::getEM()->flush();
                // Update the order if transaction success
                $this->getOrder()->markAsOrder();
                \XLite\Core\Database::getEM()->flush();
            }
        }
        // Check return from targetpay
        if (!empty($request->trxid)){
            $sale = (new \XLite\Module\TargetPay\Payment\Model\TargetPaySale())->findByTargetPayId($request->trxid);
            if ($sale != null){
                $this->initTargetPayment();
                $paid = $this->targetPayCore->checkPayment($request->trxid);
                // For Bankwire, making transaction as success
                $transaction->setStatus($transaction::STATUS_SUCCESS);
                $this->getOrder()->markAsOrder();
                // Commit the transaction
                \XLite\Core\Database::getEM()->flush();
                if ($paid) {
                    // Update local as paid
                    $sale->paid = new \DateTime("now");
                    $sale->status = $transaction::STATUS_SUCCESS;
                    \XLite\Core\Database::getRepo('\XLite\Module\TargetPay\Payment\Model\TargetPaySale')->update($sale);
                    \XLite\Core\Database::getEM()->flush();
                    // Update the order if transaction success
                    $this->getOrder()->setPaymentStatus($transaction::STATUS_SUCCESS);
                    \XLite\Core\Database::getEM()->flush();
                }
            }
        }
    }

    /**
     * Get the result message
     *
     * @param \XLite\Model\Payment\Transaction $transaction
     */
    private function getResultMessage($sale)
    {
        if(!empty($sale->more)){
            list($trxid, $accountNumber, $iban, $bic, $beneficiary, $bank) = explode("|", $sale->more);
            $total_amount = $this->getOrder()->getTotal();
            $customer_email = $this->getOrder()->getProfile()->getLogin();
            return <<<HTML
<div class="bankwire-info" style = "padding: 50px; line-height:1.5em;">
    <h4 style="padding-bottom: 20px; font-weight: bold; font-size: 150%;">Thank you for ordering in our webshop!</h4>
    <p>
        You will receive your order as soon as we receive payment from the bank. <br>
        Would you be so friendly to transfer the total amount of €  $total_amount to the bankaccount <b> $bic </b> in name of $beneficiary* ?
    </p>
    <p>
        State the payment feature <b>$trxid</b>, this way the payment can be automatically processed.<br>
        As soon as this happens you shall receive a confirmation mail on $customer_email.
    </p>
    <p>
        If it is necessary for payments abroad, then the BIC code from the bank $iban and the name of the bank is $bank.
    <p>
        <i>* Payment for our webstore is processed by TargetMedia. TargetMedia is certified as a Collecting Payment Service Provider by Currence. This means we set the highest security standards when is comes to security of payment for you as a customer and us as a webshop.</i>
    </p>
</div>
HTML;
        }
        return null;
    }


    /**
     * Print confirm information to existing html
     */
    private function printConfirmInformation()
    {
        return <<<HTML
<div style="display: none;" id="bank-wire-information"> $this->confirmation_message </div>
<script>
    window.onload = function()
    {
        var main =     document.getElementById("main")
                    || document.getElementById("main-wrapper")
                    || document.getElementById("page")
                    || document.getElementById("page-wrapper")
                    || document.body;
        if(main){
            main.innerHTML = document.getElementById("bank-wire-information").innerHTML;
        } else {
            window.location.href = window.location.href + "&show_warning=true"
        }
    };
</script>
HTML;
    }
}
