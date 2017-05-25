{* $Id: cc_targetpay_ideal.tpl,v 1.0 2015/12/16 letun Exp $ *}
<h1>Targetpay</h1>
{$lng.txt_cc_configure_top_text}
<p />
{capture name=dialog}
<form action="cc_processing.php?cc_processor={$smarty.get.cc_processor|escape:"url"}" method="post">

<center>
<table cellspacing="10">

<tr>
<td>RTLO:</td>
<td><input type="text" name="param01" size="24" maxlength="20" value="{$module_data.param01|escape|default:93929}" /></td>
</tr>


<tr>
<td>Notificatie URL:</td>
<td>{$http_location}/payment/cc_targetpay_paysafe.php</td>
</tr>

<tr>
<td>{$lng.lbl_cc_testlive_mode}:</td>
<td>
<select name="testmode">
<option value="Y"{if $module_data.testmode eq "Y"} selected="selected"{/if}>{$lng.lbl_cc_testlive_test}</option>
<option value="N"{if $module_data.testmode eq "N"} selected="selected"{/if}>{$lng.lbl_cc_testlive_live}</option>
</select>
</td>
</tr>

</table>

<p />
<input type="submit" value="{$lng.lbl_update|strip_tags:false|escape}" />
</center>
</form>
{/capture}
{include file="dialog.tpl" title=$lng.lbl_cc_settings content=$smarty.capture.dialog extra='width="100%"'}
