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

class Sofort extends TargetPayPlugin
{

    /**
     * The contructor
     */
    public function __construct()
    {
        $this->payMethod = "DEB";
        $this->appId = "382a92214fcbe76a32e22a30e1e9dd9f";
        $this->currency = "EUR";
        $this->language = 'nl';
        $this->allow_nobank = true;
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
        return 'modules/TargetPay/Payment/Sofort.twig';
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
        return 'modules/TargetPay/Payment/checkout/Sofort.twig';
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
        $this->handlePaymentResult($transaction, false);
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
        $this->handlePaymentResult($transaction, true);
    }
}
