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
 */

require_once 'targetpay.processor.class.php';
(new targetpay_processor("paysafe", "WAL", "Targetpay - Paysafe"))->handleRequest();
