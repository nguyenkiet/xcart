<?php
/* vim: set ts=4 sw=4 sts=4 et: */
/**
 * ***************************************************************************\
 * +-----------------------------------------------------------------------------+
 * | X-Cart Software license agreement |
 * | Copyright (c) 2001-2012 Qualiteam software Ltd <info@x-cart.com> |
 * | All rights reserved. |
 * +-----------------------------------------------------------------------------+
 * | PLEASE READ THE FULL TEXT OF SOFTWARE LICENSE AGREEMENT IN THE "COPYRIGHT" |
 * | FILE PROVIDED WITH THIS DISTRIBUTION. THE AGREEMENT TEXT IS ALSO AVAILABLE |
 * | AT THE FOLLOWING URL: http://www.x-cart.com/license.php |
 * | |
 * | THIS AGREEMENT EXPRESSES THE TERMS AND CONDITIONS ON WHICH YOU MAY USE THIS |
 * | SOFTWARE PROGRAM AND ASSOCIATED DOCUMENTATION THAT QUALITEAM SOFTWARE LTD |
 * | (hereinafter referred to as "THE AUTHOR") OF REPUBLIC OF CYPRUS IS |
 * | FURNISHING OR MAKING AVAILABLE TO YOU WITH THIS AGREEMENT (COLLECTIVELY, |
 * | THE "SOFTWARE"). PLEASE REVIEW THE FOLLOWING TERMS AND CONDITIONS OF THIS |
 * | LICENSE AGREEMENT CAREFULLY BEFORE INSTALLING OR USING THE SOFTWARE. BY |
 * | INSTALLING, COPYING OR OTHERWISE USING THE SOFTWARE, YOU AND YOUR COMPANY |
 * | (COLLECTIVELY, "YOU") ARE ACCEPTING AND AGREEING TO THE TERMS OF THIS |
 * | LICENSE AGREEMENT. IF YOU ARE NOT WILLING TO BE BOUND BY THIS AGREEMENT, DO |
 * | NOT INSTALL OR USE THE SOFTWARE. VARIOUS COPYRIGHTS AND OTHER INTELLECTUAL |
 * | PROPERTY RIGHTS PROTECT THE SOFTWARE. THIS AGREEMENT IS A LICENSE AGREEMENT |
 * | THAT GIVES YOU LIMITED RIGHTS TO USE THE SOFTWARE AND NOT AN AGREEMENT FOR |
 * | SALE OR FOR TRANSFER OF TITLE. THE AUTHOR RETAINS ALL RIGHTS NOT EXPRESSLY |
 * | GRANTED BY THIS AGREEMENT. |
 * +-----------------------------------------------------------------------------+
 * \****************************************************************************
 */

/**
 * Targetpay
 *
 * @category X-Cart
 * @package X-Cart
 * @subpackage Payment interface
 * @author Michel Westerink <support@idealplugins.nl>
 * @copyright Copyright (c) 2015 <support@idealplugins.nl>
 * @license http://www.x-cart.com/license.php X-Cart license agreement
 * @version $Id: cc_targetpay_ideal.php,v 1.0.0 2015/12/16 14:00:00 aim Exp $
 * @link http://www.targetpay.com/
 * @see ____file_see____
 *
 */
require_once './auth.php';

class targetpay_processor
{

    public $target_processor_file_name;

    public $target_processor_code;

    public $target_processor_method_name;

    /**
     * Contructor
     * @param unknown $filename
     * @param unknown $code
     * @param unknown $methodname
     */
    public function __construct($filename, $code, $methodname)
    {
        $this->target_processor_code = $code;
        $this->target_processor_file_name = $filename;
        $this->target_processor_method_name = $methodname;
    }
    
    /**
     * Clean input string
     * @param unknown $str
     * @return unknown
     */
    public function clean($str)
    {
        $str = @trim($str);
        
        if (get_magic_quotes_gpc()) {
            $str = stripslashes($str);
        }
        
        return mysql_real_escape_string(($str));
    }

    /**
     * Handle payment process
     */
    public function handleRequest()
    {
        global $sql_tbl, $cart, $secure_oid, $current_location, $xcart_dir;
        
        $module_params = func_query_first("SELECT * FROM $sql_tbl[ccprocessors] WHERE processor='cc_targetpay_$this->target_processor_file_name.php'");
        
        if (! isset($REQUEST_METHOD)) {
            $REQUEST_METHOD = $_SERVER["REQUEST_METHOD"];
        }
        
        if (! func_is_active_payment("cc_targetpay_$this->target_processor_file_name.php")) {
            exit();
        }
        
        if ($REQUEST_METHOD == "GET" && isset($_GET['trxid']) && ($_GET['return'] == 'success' || $_GET['return'] == 'cancel') || $_GET['return'] == 'callback') {
            // Load X-Cart data
            x_load("http");
            x_session_register("cart");
            x_session_register("secure_oid");
            
            // X-Cart data for bill | REQUIRED!
            $bill_output = array();
            $trxid = ((isset($_GET['trxid']) && ! empty($_GET['trxid'])) ? $_GET['trxid'] : ((isset($_POST['trxid']) && ! empty($_POST['trxid'])) ? $_POST['trxid'] : false));
            
            $sql = "SELECT * FROM `targetpay_sales` WHERE `targetpay_txid` = '" . $trxid . "'";
            $result = db_query($sql);
            if (mysql_num_rows($result) != 1) {
                echo 'Error, no entry found with targetpay id: ' . htmlspecialchars($trxid);
                exit();
            }
            
            $tpOrder = mysql_fetch_object($result);
            
            include_once dirname(__FILE__) . '/targetpay.class.php';
            $targetPay = new TargetPayCore($this->target_processor_code, $module_params['param01'], "382a92214fcbe76a32e22a30e1e9dd9f", "nl", $testmode);
            
            $paid = $targetPay->checkPayment($trxid);
            if ($paid) {
                $status = 'success';
                $status_code = 1;
                $bill_message = 'Accepted';
            } else {
                $status_code = 2;
                $status = 'open';
                $bill_message = $targetPay->getErrorMessage();
            }
            $sessionID_check = db_query("IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = $sql_tbl[cc_pp3_data] AND COLUMN_NAME = 'sessid')");
            $sessionName = 'sessid';
            if ($sessionID_check) {
                $sessionName = 'sessionid';
            }
            
            $bill_output['sessid'] = $sessionid;
            $bill_output["billmes"] = $status . " (" . date("d/m/Y h:i:s") . ")";
            $bill_output["code"] = $status_code;
            
            // Update payment method
            db_query("UPDATE $sql_tbl[orders] SET payment_method = '$this->target_processor_method_name' WHERE orderid='" . $this->clean($tpOrder->order_id) . "' LIMIT 1");
            $skey = $tpOrder->order_id;
            
            if ($_GET['return'] == 'success' || $_GET['return'] == 'cancel') {
                if ($paid) {
                    if (! function_exists(func_change_order_status)) {
                        include_once $xcart_dir . '/include/func/func.order.php';
                    }
                    
                    func_change_order_status($tpOrder->order_id, 'P');
                } else {
                    $sessionID_check = "IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = $sql_tbl[cc_pp3_data] AND COLUMN_NAME = 'sessid')";
                    $sessionName = 'sessionid';
                    if ($sessionID_check) {
                        $sessionName = 'sessid';
                    }
                    $query = db_query("SELECT * FROM $sql_tbl[cc_pp3_data] WHERE ref='$skey'");
                    $qResult = db_fetch_array($query);
                    db_query("UPDATE $sql_tbl[cc_pp3_data] SET param1 = 'error_message.php?$XCART_SESSION_NAME=$qResult[$sessionName]&error=error_ccprocessor_error&bill_message=Order+is+cancelled+', param3 = 'error' , is_callback = 'N' WHERE ref = '$skey'");
                }
                include $xcart_dir . "/payment/payment_ccend.php";
                exit();
            } elseif ($_GET['return'] == 'callback') {
                // Update status to "processed"
                if ($paid) {
                    func_change_order_status($tpOrder->order_id, 'P');
                    echo 'Paid...';
                } else {
                    func_change_order_status($tpOrder->order_id, 'D');
                    echo 'Received';
                }
                die();
            }
        } else {
            if (! defined('XCART_START')) {
                header("Location: ../");
                die("Access denied");
            }
            
            x_load("http");
            x_session_register("cart");
            x_session_register("secure_oid");
            
            include_once dirname(__FILE__) . '/targetpay.class.php';
            if ($module_params['testmode'] == 'Y') {
                $testmode = true;
            } else {
                $testmode = false;
            }
            
            $targetPay = new TargetPayCore($this->target_processor_code, $module_params['param01'], "382a92214fcbe76a32e22a30e1e9dd9f", "nl", $testmode);
            
            $amount = round(100 * $cart['total_cost']);
            $targetPay->setAmount($amount);
            $targetPay->setDescription('Order #' . $secure_oid[0]);
            $targetPay->setReturnUrl($current_location . "/payment/cc_targetpay_$this->target_processor_file_name.php?return=success");
            $targetPay->setCancelUrl($current_location . "/payment/cc_targetpay_$this->target_processor_file_name.php?return=cancel");
            $targetPay->setReportUrl($current_location . "/payment/cc_targetpay_$this->target_processor_file_name.php?return=callback");
            
            $url = $targetPay->startPayment(true);
            
            $sessionID_check = db_query("IF NOT EXISTS (SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = $sql_tbl[cc_pp3_data] AND COLUMN_NAME = 'sessid')");
            $sessionName = 'sessid';
            if ($sessionID_check) {
                $sessionName = 'sessionid';
            }
            
            if ($url) {
                db_query("REPLACE INTO $sql_tbl[cc_pp3_data] (ref,$sessionName,trstat) VALUES ('" . addslashes($secure_oid[0]) . "','" . $XCARTSESSID . "','TPIDE|" . implode('|', $secure_oid) . "')");
                
                $sql = "CREATE TABLE IF NOT EXISTS `targetpay_sales` (
				  `id` int(11) NOT NULL AUTO_INCREMENT,
				  `order_id` varchar(64) NOT NULL DEFAULT '',
				  `method` varchar(6) DEFAULT NULL,
				  `amount` int(11) DEFAULT NULL,
				  `targetpay_txid` varchar(64) DEFAULT NULL,
				  `targetpay_response` varchar(128) DEFAULT NULL,
				  `paid` datetime DEFAULT NULL,
				  PRIMARY KEY (`id`),
				  KEY `order_id` (`order_id`)
				) ENGINE=InnoDB";
                db_query($sql);
                
                $sql = "INSERT INTO `targetpay_sales` SET `order_id` = '" . $secure_oid[0] . "', `method` = '$this->target_processor_code', `amount` = '" . $amount . "', `targetpay_txid` = '" . $targetPay->getTransactionId() . "'";
                db_query($sql);
                
                func_header_location($url);
                exit();
            } else {
                echo $targetPay->getErrorMessage();
            }
        }
        exit();
    }
}
