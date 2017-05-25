<?php
namespace XLite\Module\TargetPay\Payment\Base;

/**
 * @file Provides support for TargetPay iDEAL, Mister Cash and Sofort Banking
 *
 * @author Yellow Melon B.V.
 *         @url http://www.idealplugins.nl
 *         @release 11-09-2014
 *         @ver 2.4
 *
 *         Changes:
 *
 *         v2.1 Cancel url added
 *         v2.2 Verify Peer disabled, too many problems with this
 *         v2.3 Added paybyinvoice (achteraf betalen) and paysafecard (former Wallie)
 *         v2.4 Removed IP_range and deprecated checkReportValidity . Because it is bad practice.
 */

/**
 * @class TargetPay Core class
 */
class TargetPayCore
{
    // Constants
    const MIN_AMOUNT = 84;

    const ERR_NO_AMOUNT = "Geen bedrag meegegeven | No amount given";

    const ERR_NO_DESCRIPTION = "Geen omschrijving meegegeven | No description given";

    const ERR_AMOUNT_TOO_LOW = "Bedrag is te laag | Amount is too low";

    const ERR_NO_RTLO = "Geen rtlo (layoutcode TargetPay) bekend; controleer de module instellingen | No rtlo (layoutcode TargetPay) filled in, check the module settings";

    const ERR_NO_TXID = "Er is een onjuist transactie ID opgegeven | An incorrect transaction ID was given";

    const ERR_NO_RETURN_URL = "Geen of ongeldige return URL | No or invalid return URL";

    const ERR_NO_REPORT_URL = "Geen of ongeldige report URL | No or invalid report URL";

    const ERR_IDEAL_NO_BANK = "Geen bank geselecteerd voor iDEAL | No bank selected for iDEAL";

    const ERR_SOFORT_NO_COUNTRY = "Geen land geselecteerd voor Sofort | No country selected for Sofort";

    const ERR_PAYBYINVOICE = "Fout bij achteraf betalen|Error with paybyinvoice";
    
    // Constant array's
    protected $paymentOptions = array(
        "AUTO",
        "IDE",
        "MRC",
        "DEB",
        "WAL",
        "CC",
        "PYP",
        "BW",
        "AFP"
    );

    /*
     * If payMethod is set to 'AUTO' it will decided on the value of bankId
     * Then, when requested the bankId list will be filled with
     *
     * a) 'IDE' + the bank ID's for iDEAL
     * b) 'MRC' for Mister Cash
     * c) 'DEB' + countrycode for Sofort Banking, e.g. DEB49 for Germany
     */
    protected $minimumAmounts = array(
        "AUTO" => 84,
        "IDE" => 84,
        "MRC" => 49,
        "DEB" => 10,
        "WAL" => 10,
        "CC"  => 10,
        "PYP" => 84,
        "BW"  => 84,
        "AFP" => 84
    );

    public $descriptions = array(
        "IDE" => 'iDEAL',
        "MRC" => 'Mister Cash',
        "DEB" => 'Sofort Banking',
        "WAL" => 'Paysafe Card',
        "CC" => "Credit Card",
        "PYP" => "Paypal",
        "AFP" => "Afterpay",
        "BW" => "Bank Wire"
    );

    protected $checkAPIs = array(
        "IDE" => "https://www.targetpay.com/ideal/check",
        "MRC" => "https://www.targetpay.com/mrcash/check",
        "DEB" => "https://www.targetpay.com/directebanking/check",
        "WAL" => "http://www.targetpay.com/paysafecard/check",
        "CC" => "https://www.targetpay.com/creditcard_atos/check",
        "PYP" => "https://transaction.digiwallet.nl/paypal/check",
        "AFP" => "https://transaction.digiwallet.nl/afterpay/check",
        "BW" => "https://transaction.digiwallet.nl/bankwire/check"
    );
    
    // Variables
    protected $rtlo = null;

    protected $testMode = false;

    protected $language = "nl";

    protected $payMethod = "AUTO";
    // Payment Method
    protected $currency = "EUR";

    protected $bankId = null;

    protected $appId = null;

    protected $amount = 0;

    protected $description = null;

    protected $returnUrl = null;
    // When using the AUTO-setting; %payMethod% will be replaced by the actual payment method just before starting the payment
    protected $cancelUrl = null;
    // When using the AUTO-setting; %payMethod% will be replaced by the actual payment method just before starting the payment
    protected $reportUrl = null;
    // When using the AUTO-setting; %payMethod% will be replaced by the actual payment method just before starting the payment
    protected $bankUrl = null;

    protected $transactionId = null;

    protected $paidStatus = false;

    protected $consumerInfo = array();

    protected $errorMessage = null;

    protected $parameters = array();
    // Additional parameters


    /**
     * More information
     *
     * @var unknown
     */
    private $moreInformation = null;
    
    /**
     * Salt parameter for BW
     *
     * @var unknown
     */
    private $salt = null;
    
    /**
     * Response from targetpay API
     * @var unknown
     */
    private $target_response = null;
    /**
     * Constructor
     *
     * @param int $rtlo
     *            Layoutcode
     */
    public function __construct($payMethod, $rtlo = false, $appId = false, $language = "nl", $testMode = false)
    {
        $payMethod = strtoupper($payMethod);
        if (in_array($payMethod, $this->paymentOptions)) {
            $this->payMethod = $payMethod;
        } else {
            return false;
        }
        $this->rtlo = (int) $rtlo;
        $this->testMode = ($testMode) ? '1' : '0';
        $this->language = strtolower(substr($language, 0, 2));
        $this->appId = strtolower(preg_replace("/[^a-z\d_]/i", "", $appId));
    }

    /**
     * Get list with banks based on PayMethod setting (AUTO, IDE, ...
     * etc.)
     */
    public function getBankList()
    {
        $url = "https://www.targetpay.com/api/idealplugins?banklist=" . urlencode($this->payMethod) . (($this->payMethod == 'IDE') ? '&ver=3' : '');
        
        $xml = $this->httpRequest($url);
        if (! $xml) {
            $banks_array["IDE0001"] = "Bankenlijst kon niet opgehaald worden bij TargetPay, controleer of curl werkt!";
            $banks_array["IDE0002"] = "  ";
        } else {
            $banks_object = new SimpleXMLElement($xml);
            foreach ($banks_object->bank as $bank) {
                $banks_array["{$bank->bank_id}"] = "{$bank->bank_name}";
            }
        }
        return $banks_array;
    }

    /**
     * Start transaction with TargetPay
     *
     * Set at least: amount, description, returnUrl, reportUrl (optional: cancelUrl)
     * In case of iDEAL: bankId
     * In case of Sofort: countryId
     *
     * After starting, it will return a link to the bank if successfull :
     * - Link can also be fetched with getBankUrl()
     * - Get the transaction id via getTransactionId()
     * - Read the errors with getErrorMessage()
     * - Get the actual started payment method, in case of auto-setting, using getPayMethod()
     */
    public function startPayment($noBank = false)
    {
        if (! $this->rtlo) {
            $this->errorMessage = self::ERR_NO_RTLO;
            return false;
        }
        
        if (! $this->amount) {
            $this->errorMessage = self::ERR_NO_AMOUNT;
            return false;
        }
        
        if ($this->amount < $this->minimumAmounts[$this->payMethod]) {
            $this->errorMessage = self::ERR_AMOUNT_TOO_LOW;
            return false;
        }
        
        if (! $this->description) {
            $this->errorMessage = self::ERR_NO_DESCRIPTION;
            return false;
        }
        
        if (! $this->returnUrl) {
            $this->errorMessage = self::ERR_NO_RETURN_URL;
            return false;
        }
        
        if (! $this->reportUrl) {
            $this->errorMessage = self::ERR_NO_REPORT_URL;
            return false;
        }
        
        if (($this->payMethod == "IDE") && (! $this->bankId) && $noBank == 0) {
            $this->errorMessage = self::ERR_IDEAL_NO_BANK;
            return false;
        }
        
        $this->returnUrl = str_replace("%payMethod%", $this->payMethod, $this->returnUrl);
        $this->cancelUrl = str_replace("%payMethod%", $this->payMethod, $this->cancelUrl);
        $this->reportUrl = str_replace("%payMethod%", $this->payMethod, $this->reportUrl);
        
        $url = "";
        switch ($this->payMethod) {
            case 'IDE':
            case 'MRC':
            case 'DEB':
            case 'CC':
            case 'WAL':
                $url = "https://www.targetpay.com/api/idealplugins?paymethod=" . urlencode($this->payMethod);
                $url .= "&app_id=" . urlencode($this->appId);
                $url .= "&rtlo=" . urlencode($this->rtlo);
                $url .= "&bankId=" . urlencode($this->bankId);
                $url .= "&amount=" . urlencode($this->amount);
                $url .= "&description=" . urlencode($this->description);
                $url .= "&currency=" . urlencode($this->currency);
                $url .= (($this->payMethod == "IDE") ? "&ver=3&language=nl&" : "");
                $url .= (($this->payMethod == "MRC") ? "&lang=" . urlencode($this->getLanguage(array("NL", "FR", "EN"), "NL")) : "");
                $url .= (($this->payMethod == "DEB") ? "&type=1&country=49&lang=" . urlencode($this->getLanguage(array("NL", "EN", "DE" ), "DE")) : "");
                $url .= "&userip=" . urlencode($_SERVER["REMOTE_ADDR"]);
                $url .= "&domain=" . urlencode($_SERVER["HTTP_HOST"]);
                $url .= "&returnurl=" . urlencode($this->returnUrl);
                $url .= ((! empty($this->cancelUrl)) ? "&cancelurl=" . urlencode($this->cancelUrl) : "");
                $url .= "&reporturl=" . urlencode($this->reportUrl);
                break;
            case 'PYP':
                $url = 'https://transaction.digiwallet.nl/paypal/start?ver=1&rtlo='. urlencode($this->rtlo);
                $url .= "&" . 'amount=' . urlencode($this->amount);
                $url .= "&" . "description=" . urlencode($this->description);
                $url .= "&" . "test=" . $this->testMode;
                $url .= "&" . "returnurl=" . urlencode($this->returnUrl);
                $url .= ((! empty($this->cancelUrl)) ? "&cancelurl=" . urlencode($this->cancelUrl) : "");
                $url .= "&" . "reporturl=" . urlencode($this->reportUrl);
                break;
            case 'BW':
                $url = 'https://transaction.digiwallet.nl/bankwire/start?ver=2&rtlo=' . urlencode($this->rtlo);
                $url .= "&" . 'amount=' . urlencode($this->amount);
                $url .= "&" . "description=" . urlencode($this->description);
                $url .= "&" . "test=" . $this->testMode;
                $url .= ((! empty($this->salt)) ? "&salt=" . urlencode($this->salt) : "");
                $url .= "&" . "returnurl=" . urlencode($this->returnUrl);
                $url .= ((! empty($this->cancelUrl)) ? "&cancelurl=" . urlencode($this->cancelUrl) : "") ;
                $url .= "&" . "reporturl=" . urlencode($this->reportUrl);
                break;
            case 'AFP':
                $url = 'https://transaction.digiwallet.nl/afterpay/start?ver=1&rtlo=' . urlencode($this->rtlo);
                $url .= "&" . 'amount=' . urlencode($this->amount);
                $url .= "&" . "description=" . urlencode($this->description);
                $url .= "&" . "test=" . $this->testMode;
                $url .= "&" . "returnurl=" . urlencode($this->returnUrl);
                $url .= ((! empty($this->cancelUrl)) ? "&cancelurl=" . urlencode($this->cancelUrl) : "");
                $url .= "&" . "reporturl=" . urlencode($this->reportUrl);
                break;
        }
        
        
        if (is_array($this->parameters)) {
            foreach ($this->parameters as $k => $v) {
                $url .= "&" . $k . "=" . urlencode($v);
            }
        }
        
        $result = $this->httpRequest($url);
        //$result = "000000 0394-11-AA-7641|5940.74.231|NL44ABNA0594074231|ABNANL2A|St. Derdengelden TargetMedia|ABN Amro";
        
        $this->target_response = $result;
        
        if (substr($result, 0, 6) == "000000") {
            $result = substr($result, 7);
            if ($this->payMethod == 'AFP' || $this->payMethod == 'BW') {
                $this->moreInformation = $result;
                return true; // Process later
            } else {
                list ($this->transactionId, $this->bankUrl) = explode("|", $result);
            }
            return $this->bankUrl;
        } else {
            $this->errorMessage = "TargetPay antwoordde: " . $result . " | TargetPay responded with: " . $result;
            return false;
        }
    }

    /**
     * Check transaction with TargetPay
     *
     * @param string $payMethodId
     *            Payment method's see above
     * @param string $transactionId
     *            Transaction ID to check Returns true if payment successfull (or testmode) and false if not After payment: - Read the errors with getErrorMessage() - Get user information using getConsumerInfo() Returns true if payment successfull (or testmode) and false if not After payment: - Read the errors with getErrorMessage() - Get user information using getConsumerInfo()
     *
     *            Returns true if payment successfull (or testmode) and false if not
     *
     *            After payment:
     *            - Read the errors with getErrorMessage()
     *            - Get user information using getConsumerInfo()
     */
    public function checkPayment($transactionId)
    {
        if (! $this->rtlo) {
            $this->errorMessage = self::ERR_NO_RTLO;
            return false;
        }
        
        if (! $transactionId) {
            $this->errorMessage = self::ERR_NO_TXID;
            return false;
        }
        
        $url = $this->checkAPIs[$this->payMethod] . "?" . "rtlo=" . urlencode($this->rtlo) . "&" . "trxid=" . urlencode($transactionId) . "&" . "once=0&" . "test=" . (($this->testMode) ? "1" : "0");
        
        $result = $this->httpRequest($url);
        
        $this->target_response = $result;
        
        if ($this->payMethod == 'AFP'){
            // Stop checking status and transfer result to Afterpay Model to process
            return $result;
        }
        
        $_result = explode("|", $result);
        
        $consumerBank = "";
        $consumerName = "";
        $consumerCity = "NOT PROVIDED";
        
        if (count($_result) == 4) {
            list ($resultCode, $consumerBank, $consumerName, $consumerCity) = $_result;
        } else {
            list ($resultCode) = $_result;
        }
        
        $this->consumerInfo["bankaccount"] = "bank";
        $this->consumerInfo["name"] = "customername";
        $this->consumerInfo["city"] = "city";
        
        if ($resultCode == "000000 OK") {
            $this->consumerInfo["bankaccount"] = $consumerBank;
            $this->consumerInfo["name"] = $consumerName;
            $this->consumerInfo["city"] = ($consumerCity != "NOT PROVIDED") ? $consumerCity : "";
            $this->paidStatus = true;
            return true;
        } else {
            $this->paidStatus = false;
            $this->errorMessage = $result;
            return false;
        }
    }

    /**
     * [DEPRECATED] checkReportValidity
     * Will removed in future versions
     * This function used to act as a redundant check on the validity of reports by checking IP addresses
     * Because this is bad practice and not necessary it is now removed
     */
    public function checkReportValidity($post, $server)
    {
        return true;
    }

    /**
     * PRIVATE FUNCTIONS
     */
    protected function httpRequest($url, $method = "GET")
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 100);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        if ($method == "POST") {
            curl_setopt($ch, CURLOPT_POST, 1);
        }
        $result = curl_exec($ch);
        curl_close($ch);
        return $result;
    }

    /**
     * GETTERS & SETTERS
     */
    public function setAmount($amount)
    {
        $this->amount = round($amount);
        return true;
    }

    /**
     * Bind additional parameter to start request.
     * Safe for chaining.
     */
    public function bindParam($name, $value)
    {
        $this->parameters[$name] = $value;
        return $this;
    }

    public function getAmount()
    {
        return $this->amount;
    }

    /**
     * Get more information (for AFP and BW)
     * @return \XLite\Module\TargetPay\Payment\Base\unknown
     */
    public function getMoreInformation(){
        return $this->moreInformation;
    }
    public function overwritePayMethod($paymethod)
    {
        $this->payMethod = $paymethod;
    }

    public function setBankId($bankId)
    {
        // Handle 'mixed' setting => therefore the payMethod must be 'AUTO'
        if ($this->payMethod == "AUTO") {
            $bankId = strtoupper($bankId);
            if (substr($bankId, 0, 3) == "IDE") {
                $this->payMethod = "IDE";
                $this->bankId = substr($bankId, 3, strlen($bankId));
                return true;
            } elseif (substr($bankId, 0, 3) == "AFT") {
                $this->payMethod = "AFT";
                $this->bankId = substr($bankId, 3, 4);
                return true;
            } elseif ($bankId == "MRC") {
                $this->payMethod = "MRC";
                $this->bankId = false;
                return true;
            } elseif (substr($bankId, 0, 3) == "DEB") {
                $this->payMethod = "DEB";
                $this->bankId = false;
                $this->countryId = substr($bankId, 3, 2);
                return true;
            } elseif ($bankId == "CC") {
                $this->payMethod = "CC";
                $this->bankId = false;
                return true;
            } elseif ($bankId == "WAL") {
                $this->payMethod = "WAL";
                $this->bankId = false;
                return true;
            }
        } else {
            $this->bankId = substr($bankId, 0, 4);
            return true;
        }
    }

    public function getBankId()
    {
        return $this->bankId;
    }

    public function getBankUrl()
    {
        return $this->bankUrl;
    }

    public function getConsumerInfo()
    {
        return $this->consumerInfo;
    }

    public function setCountryId($countryId)
    {
        $this->countryId = strtolower(substr($countryId, 0, 2));
        return true;
    }

    public function getCountryId()
    {
        return $this->countryId;
    }

    public function setCurrency($currency)
    {
        $this->currency = strtoupper(substr($currency, 0, 3));
        return true;
    }

    public function getCurrency()
    {
        return $this->currency;
    }

    public function setDescription($description)
    {
        $this->description = substr($description, 0, 32);
        return true;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function getErrorMessage()
    {
        if ($this->language == "nl") {
            list ($returnVal) = explode(" | ", $this->errorMessage, 2);
        } elseif ($this->language == "en") {
            list ($discard, $returnVal) = explode(" | ", $this->errorMessage, 2);
        } else {
            $returnVal = $this->errorMessage;
        }
        return $returnVal;
    }

    public function getLanguage($allowList = false, $defaultLanguage = false)
    {
        if (! $allowList) {
            return $this->language;
        } else {
            if (in_array(strtoupper($this->language), $allowList)) {
                return strtoupper($this->language);
            } else {
                return $this->defaultLanguage;
            }
        }
    }

    public function getPaidStatus()
    {
        return $this->paidStatus;
    }

    public function getPayMethod()
    {
        return $this->payMethod;
    }

    public function setReportUrl($reportUrl)
    {
        if (preg_match('|(\w+)://([^/:]+)(:\d+)?(.*)|', $reportUrl)) {
            $this->reportUrl = $reportUrl;
            return true;
        } else {
            return false;
        }
    }

    public function getReportUrl()
    {
        return $this->reportUrl;
    }

    public function setReturnUrl($returnUrl)
    {
        if (preg_match('|(\w+)://([^/:]+)(:\d+)?(.*)|', $returnUrl)) {
            $this->returnUrl = $returnUrl;
            return true;
        } else {
            return false;
        }
    }

    public function getReturnUrl()
    {
        return $this->returnUrl;
    }

    public function setCancelUrl($cancelUrl)
    {
        if (preg_match('|(\w+)://([^/:]+)(:\d+)?(.*)|', $cancelUrl)) {
            $this->cancelUrl = $cancelUrl;
            return true;
        } else {
            return false;
        }
    }

    public function getCancelUrl()
    {
        return $this->cancelUrl;
    }

    public function setTransactionId($transactionId)
    {
        $this->transactionId = substr($transactionId, 0, 32);
        return true;
    }

    public function getTransactionId()
    {
        return $this->transactionId;
    }
    
    /**
     * Get the target response
     * @return \XLite\Module\TargetPay\Payment\Base\unknown
     */
    public function getTargetResponse()
    {
        return $this->target_response;
    }
}
