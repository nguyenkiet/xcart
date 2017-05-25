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
use XLite\Module\TargetPay\Payment\Model\AfterpayValidationException;

class Afterpay extends TargetPayPlugin
{

    /**
     * Tax applying percent
     * @var array
     */
    protected $array_tax = [
        1 => 21,
        2 => 6,
        3 => 0,
        4 => 'none'
    ];

    /**
     * Error reject
     * @var unknown
     */
    protected $reject_error;

    /**
     * Redirect to enrichment url
     * @var unknown
     */
    protected $enrichment_url;
    /**
     * The contructor
     */
    public function __construct()
    {
        $this->payMethod = "AFP";
        $this->appId = "382a92214fcbe76a32e22a30e1e9dd9f";
        $this->currency = "EUR";
        $this->language = 'nl';
        $this->allow_nobank = true;
    }

    /***
     * Get product tax by Targetpay
     * @param unknown $val
     * @return number
     */
    private function getTax($val)
    {
        if(empty($val)) return 4; // No tax
        else if($val >= 21) return 1;
        else if($val >= 6) return 2;
        else return 3;
    }
    /**
     * Init some params for starting payment
     * {@inheritDoc}
     * @see \XLite\Module\TargetPay\Payment\Base\TargetPayPlugin::getFormURL()
     */
    protected function getFormURL()
    {
        $this->initTargetPayment();
        // Add invoice lines to payment
        $invoice_lines = null;
        $items = $this->getOrder()->getItems();
        foreach ($items as $item) {
            $invoice_lines[] = [
                'productCode' => $item->getProduct()->getId(),
                'productDescription' => $item->getProduct()->getName(),
                'quantity' => (int) $item->getAmount(),
                'price' => $item->getPrice(),
                'taxCategory' => $this->getTax(100 * $this->getOrder()->getSurchargeSum() / $this->getOrder()->getSubtotal())
            ];
        }
        if($invoice_lines != null && !empty($invoice_lines)){
            $this->targetPayCore->bindParam('invoicelines', json_encode($invoice_lines));
        }

        // Build billing address
        $billingstreet = "";
        $billingpostcode = "";
        $billingcity = "";
        $billingsurename = "";
        $billingcountrycode = "";
        $billingphonenumber = "";
        // Build shipping address
        $shippingstreet = "";
        $shippingpostcode = "";
        $shippingcity = "";
        $shippingsurename = "";
        $shippingcountrycode = "";
        $shippingphonenumber = "";
        foreach ($this->getOrder()->getAddresses() as $add){
            /** @var \XLite\Model\Address $add */
            if($add->getIsBilling()) {
                $item = $this->getAddressSectionData($add);
                if(!empty($add->getCountry())){
                    $billingcountrycode = $add->getCountry()->getCode3();
                }
                $billingstreet = isset($item['street']) ? $item['street']['value'] : "";
                $billingpostcode = isset($item['zipcode']) ? $item['zipcode']['value'] : "";
                $billingcity = isset($item['city']) ? $item['city']['value'] : "";
                $billingsurename = isset($item['firstname']) ? $item['firstname']['value'] : "";
                $billingsurename .= isset($item['lastname']) ? " " . $item['lastname']['value'] : "";
                $billingphonenumber = isset($item['phone']) ? " " . $item['phone']['value'] : "";
            }
            // Shipping address
            if($add->getIsShipping()) {
                $item = $this->getAddressSectionData($add);
                if(!empty($add->getCountry())){
                    $shippingcountrycode = $add->getCountry()->getCode3();
                }
                $shippingstreet = isset($item['street']) ? $item['street']['value'] : "";
                $shippingpostcode = isset($item['zipcode']) ? $item['zipcode']['value'] : "";
                $shippingcity = isset($item['city']) ? $item['city']['value'] : "";
                $shippingsurename = isset($item['firstname']) ? $item['firstname']['value'] : "";
                $shippingsurename .= isset($item['lastname']) ? " " . $item['lastname']['value'] : "";
                $shippingphonenumber = isset($item['phone']) ? " " . $item['phone']['value'] : "";
            }
        }
        $this->targetPayCore->bindParam('billingstreet', $billingstreet);
        $this->targetPayCore->bindParam('billinghousenumber', "");
        $this->targetPayCore->bindParam('billingpostalcode', $billingpostcode);
        $this->targetPayCore->bindParam('billingcity', $billingcity);
        $this->targetPayCore->bindParam('billingpersonemail', $this->getOrder()->getProfile()->getLogin());
        $this->targetPayCore->bindParam('billingpersoninitials', "");
        $this->targetPayCore->bindParam('billingpersongender', "");
        $this->targetPayCore->bindParam('billingpersonsurname', $billingsurename);
        $this->targetPayCore->bindParam('billingcountrycode', $billingcountrycode);
        $this->targetPayCore->bindParam('billingpersonlanguagecode', "");
        $this->targetPayCore->bindParam('billingpersonbirthdate', "");
        $this->targetPayCore->bindParam('billingpersonphonenumber', $billingphonenumber);
        // Build shipping address
        $this->targetPayCore->bindParam('shippingstreet', $shippingstreet);
        $this->targetPayCore->bindParam('shippinghousenumber', "");
        $this->targetPayCore->bindParam('shippingpostalcode', $shippingpostcode);
        $this->targetPayCore->bindParam('shippingcity', $shippingcity);
        $this->targetPayCore->bindParam('shippingpersonemail', $this->getOrder()->getProfile()->getLogin());
        $this->targetPayCore->bindParam('shippingpersoninitials', "");
        $this->targetPayCore->bindParam('shippingpersongender', "");
        $this->targetPayCore->bindParam('shippingpersonsurname', $shippingsurename);
        $this->targetPayCore->bindParam('shippingcountrycode', $shippingcountrycode);
        $this->targetPayCore->bindParam('shippingpersonlanguagecode', "");
        $this->targetPayCore->bindParam('shippingpersonbirthdate', "");
        $this->targetPayCore->bindParam('shippingpersonphonenumber', $shippingphonenumber);

        return parent::getFormURL();
    }


    /**
     * Return specific data for address entry. Helper.
     *
     * @param \XLite\Model\Address $address   Address
     * @param boolean              $showEmpty Show empty fields OPTIONAL
     *
     * @return array
     */
    protected function getAddressSectionData(\XLite\Model\Address $address, $showEmpty = false)
    {
        $result = array();
        $hasStates = $address->getCountry() ? $address->getCountry()->hasStates() : false;

        foreach (\XLite\Core\Database::getRepo('XLite\Model\AddressField')->findAllEnabled() as $field) {
            $method = 'get'
                . \Includes\Utils\Converter::convertToCamelCase(
                    $field->getViewGetterName() ?: $field->getServiceName()
                    );
                $addressFieldValue = $address->{$method}();

                switch ($field->getServiceName()) {
                    case 'state_id':
                        $addressFieldValue = $hasStates ? $addressFieldValue : null;
                        if (null === $addressFieldValue && $hasStates) {
                            $addressFieldValue = $address->getCustomState();
                        }
                        break;

                    case 'custom_state':
                        $addressFieldValue = $hasStates ? null : $address->getCustomState();
                        break;
                    default:
                }

                if (strlen($addressFieldValue) || $showEmpty) {
                    $result[$field->getServiceName()] = array(
                        'title'     => $field->getName(),
                        'value'     => $addressFieldValue
                    );
                }
        }

        return $result;
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
        return 'modules/TargetPay/Payment/Afterpay.twig';
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
        return 'modules/TargetPay/Payment/checkout/Afterpay.twig';
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
     * Process Afterpay result
     *
     * @param \XLite\Model\Payment\Transaction $transaction
     * @param unknown $callback
     */
    public function processPayment(\XLite\Model\Payment\Transaction $transaction, $callback = false)
    {
        $request = \XLite\Core\Request::getInstance();
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
                    list ($status, $trxid) = explode("|", $sale->more);
                    $sale->targetpay_txid = $trxid;
                    $sale->status = $transaction::STATUS_INPROGRESS;
                    \XLite\Core\Database::getRepo('\XLite\Module\TargetPay\Payment\Model\TargetPaySale')->update($sale);

                    if (strtolower($status) != "captured") {
                        list ($status, $trxid, $ext_info) = explode("|", $sale->more);
                        if (strtolower($status) == "rejected") {
                            $this->reject_error = $ext_info;
                            // Show error message to customer page
                            $errors = new AfterpayValidationException(json_decode($this->reject_error, true));
                            $error_msg = $this->reject_error;
                            if($errors->IsValidationError()){
                                $error_msg = "";
                                foreach ($errors->getErrorItems() as $message) {
                                    $error_msg .= "<br/>";
                                    $error_msg .= (is_array($message)) ? implode(", ", $message) : $message;
                                }
                            }
                            \XLite\Core\TopMessage::addError("The order has been rejected with the reason: " . $error_msg);
                        } else {
                            $this->enrichment_url = $ext_info;
                            // Redirect to enrichment page
                            header("Location:" . $this->enrichment_url);
                            exit(0);
                        }
                    } else {
                        $this->initTargetPayment();
                        // Order captured. Transfer to return Url to check the payment status
                        $return_url = $this->targetPayCore->getReturnUrl() . '&trxid=' . $trxid;
                        header("Location:" . $return_url);
                        exit(0);
                    }
                }
            }
        }
        // Check return from targetpay
        if (!empty($request->trxid)){
            $sale = (new \XLite\Module\TargetPay\Payment\Model\TargetPaySale())->findByTargetPayId($request->trxid);
            if ($sale != null){
                $this->initTargetPayment();
                $result = $this->targetPayCore->checkPayment($request->trxid);
                $paymentStatus = false;
                $result = substr($result, 7);
                list ($invoiceKey, $invoicePaymentReference, $status) = explode("|", $result);
                if (strtolower($status) == "captured") {
                    $paymentStatus = true;
                } elseif (strtolower($status) == "incomplete") {
                    list ($invoiceKey, $invoicePaymentReference, $status, $this->enrichment_url) = explode("|", $result);
                    // Redirect to enrichment user if not callback
                    if(!$callback){
                        // Redirect to enrichment page
                        header("Location:" . $this->enrichment_url);
                        exit(0);
                    }
                } elseif (strtolower($status) == "rejected") {
                    list ($invoiceKey, $invoicePaymentReference, $status, $reject_reason, $this->reject_error) = explode("|", $result);
                    // Show error if return
                    if(!$callback){
                        // Show error message to customer page
                        $errors = new AfterpayValidationException(json_decode($this->reject_error, true));
                        $error_msg = $this->reject_error;
                        if($errors->IsValidationError()){
                            $error_msg = "";
                            foreach ($errors->getErrorItems() as $message) {
                                $error_msg .= "<br/>";
                                $error_msg .= (is_array($message)) ? implode(", ", $message) : $message;
                            }
                        }
                        \XLite\Core\TopMessage::addError("The order has been rejected with the reason: " . $error_msg);
                    }
                }
                // Check payment status
                if ($paymentStatus) {
                    // Update local as paid
                    $sale->paid = new \DateTime("now");
                    $sale->status = $transaction::STATUS_SUCCESS;
                    \XLite\Core\Database::getRepo('\XLite\Module\TargetPay\Payment\Model\TargetPaySale')->update($sale);
                    \XLite\Core\Database::getEM()->flush();
                    // Update transaction status
                    $transaction->setStatus($transaction::STATUS_SUCCESS);
                    // Update to mark transaction as Order
                    $this->getOrder()->markAsOrder();
                    // Update order tatus
                    $this->getOrder()->setPaymentStatusByTransaction($transaction);
                    $this->getOrder()->setPaymentStatus($transaction::STATUS_SUCCESS);
                    // Commit the transaction
                    \XLite\Core\Database::getEM()->flush();
                } else {
                    // Update transaction status
                    $transaction->setStatus($transaction::STATUS_INPROGRESS);
                }
            }
        }
    }
}
