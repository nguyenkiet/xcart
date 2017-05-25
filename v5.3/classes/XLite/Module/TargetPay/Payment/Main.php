<?php
// vim: set ts=4 sw=4 sts=4 et:

/**
 * @file Provides support for TargetPay iDEAL, Mister Cash, Sofort Banking, Credit and Paysafe
 *
 * @author Yellow Melon B.V.
 *         @url http://www.idealplugins.nl
 */
namespace XLite\Module\TargetPay\Payment;

/**
 * Main module
 */
abstract class Main extends \XLite\Module\AModule
{

    const PP_METHOD_IDEAL = 'Targetpay - iDEAL';

    const PP_METHOD_BANCONTACT = 'Targetpay - Bancontact';

    const PP_METHOD_CREDITCARD = 'Targetpay - Creditcard';

    const PP_METHOD_PAYSAFE = 'Targetpay - Paysafe';

    const PP_METHOD_SOFORT = 'Targetpay - Sofort';
    
    const PP_METHOD_PAYPAL = 'Targetpay - Paypal';
    
    const PP_METHOD_BANKWIRE = 'Targetpay - Bankwire';

    /**
     * Author name
     *
     * @return string
     */
    public static function getAuthorName()
    {
        return 'TargetMedia';
    }

    /**
     * Module name
     *
     * @return string
     */
    public static function getModuleName()
    {
        return 'Target Payment';
    }

    /**
     * Module description
     *
     * @return string
     */
    public static function getDescription()
    {
        return 'Enables taking payments for your online store via TargetMedia\'s services.';
    }

    /**
     * Returns payment method
     *
     * @param string $serviceName
     *            Service name
     * @param boolean $enabled
     *            Enabled status OPTIONAL
     *
     * @return \XLite\Model\Payment\Method
     */
    public static function getPaymentMethod($serviceName, $enabled = null)
    {
        if (! isset(static::$paymentMethod[$serviceName])) {
            static::$paymentMethod[$serviceName] = \XLite\Core\Database::getRepo('XLite\Model\Payment\Method')->findOneBy(array(
                'service_name' => $serviceName
            ));
            if (! static::$paymentMethod[$serviceName]) {
                static::$paymentMethod[$serviceName] = false;
            }
        }
        return static::$paymentMethod[$serviceName] && (is_null($enabled) || static::$paymentMethod[$serviceName]->getEnabled() === (bool) $enabled) ? static::$paymentMethod[$serviceName] : null;
    }

    /**
     * Get module major version
     *
     * @return string
     */
    public static function getMajorVersion()
    {
        return '5.3';
    }

    /**
     * Module version
     *
     * @return string
     */
    public static function getMinorVersion()
    {
        return '1';
    }

    /**
     * The module is defined as the payment module
     *
     * @return integer|null
     */
    public static function getModuleType()
    {
        return static::MODULE_TYPE_PAYMENT;
    }
}
