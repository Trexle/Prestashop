{*
* 2007-2013 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
*         DISCLAIMER   *
* *************************************** */
/* Do not edit or add to this file if you wish to upgrade Prestashop to newer
* versions in the future.
* ****************************************************
* @category  Payment Gateway Module
 * @package	  Trexle API Prestashop Module
 * @author    Trexle <support@trexle.com>
 * @copyright Copyright (c) 2016 - 2018 Trexle (http://trexle.com)
 * @license  GPLv2
*}

<script type="text/javascript">
//callback handler for form submit
$(document).ready(function() {

$("#trexle-form").submit(function(e)
{
    var postData = $(this).serializeArray();
    var formURL = $(this).attr("action");
    $('#cc-error').hide();
    $('#trexle-form').fadeOut('fast');
    $('#trexle-ajax-loader').show();
	//scroll to loader view - bug fix mobile
	 $('html,body').animate({
            scrollTop: $("#opc_payment_methods").offset().top},
            'fast');      
    //$('#trexle_submit').attr('disabled', 'disabled'); /* Disable the submit button to prevent repeated clicks */
    
    $.ajax(
    {
        url : formURL,
        //contentType: 'application/json; charset=utf-8',
        type: "POST",
        data : postData,
        dataType: 'json',
       
        success:function(data) 
        { 
           if (data.err == 1) 
           { 
              // Re-enable the submit button
              $('#trexle-ajax-loader').hide();
              $('#cc-error').html(data.msg).show();
              $('#trexle-form').fadeIn('fast');
             // $('#trexle_submit').removeAttr('disabled');
           } 
           else 
           {  
               window.location.href = data.msg;
           } 
        },
        error: function(jqXHR, textStatus, errorThrown) 
        {
            alert('ajax error'); //if fails      
        }
    });
    	
        return false; /* Prevent the form from submitting with the default action */
        
    });

  
});
 
</script>

<div class="trexle" id="trexle-block">
    
                      <div class="alert alert-danger clearfix" id="cc-error" style="display:none"></div>
                      
                            <div id="trexle-ajax-loader" style="display: none;"><img src="{$module_dir|escape:htmlall:'UTF-8'}views/img/ajax-loader.gif" alt="" /> {l s='Transaction in progress, please wait.' mod='trexle'}</div>
	
                            <form action="{$link->getModuleLink('trexle', 'validation', [], true)|escape:'htmlall':'UTF-8'}" method="post" name="trexle-form" id="trexle-form" class="trexle-form">

                                <input type="hidden" name="confirm" value="1" />
				
                                <div class="box">
                                    <h3 class="page-heading" style="margin-top: 10px;">{l s='PAY BY CREDIT CARD'} <img alt="" src="{$module_dir|escape:htmlall:'UTF-8'}views/img/secure-icon.png" /></h3>
                                    <h4 class="title_accept">
					{l s='We Accept' mod='trexle'}
                                    </h4>
                                    <div class="trexle-payment-logos">
						<img src="{$this_path_ssl}views/img/visa_big.gif" alt="{l s='Visa' mod='trexle'}" />
						<img src="{$this_path_ssl}views/img/mc_big.gif" alt="{l s='Mastercard' mod='trexle'}" />
                                    </div>				

                                
                                    
				<label>{l s='CARD NUMBER' mod='trexle'}</label><br />
				<input type="text" class="card-number" autocomplete="off" size="20" name="cc_number" value="{if isset($cc_number)}{$cc_number}{/if}" />
                                   

                                    <div   style="margin-top: 10px;">
                                        
					<label>{l s='Card Expiry (mm/yy)' mod='trexle'}: </label>
                                        
					<select name="cc_month" id="exp_month" >
						{foreach from=$months  key=k item=v}
							<option value="{$k}" >{$v}</option>
						{/foreach}
					</select>
                                      
					/
                                        
					<select name="cc_year" id="exp_year" >
						{foreach from=$years  key=k item=v}
							<option value="{$k}">{$v}</option>
						{/foreach}
					</select>
                                       
                                    </div>
                                        
                                  
                                   <div class="">
                                    <label>{l s='CVN code' mod='trexle'}:</label>
					<input type="text" name="cc_cvv" size="4" value="{if isset($cc_cvv)}{$cc_cvv}{/if}" />
					<span class="cvn-info">{l s='3-4 digit number found on the back of your card.' mod='trexle'}</span>
                                   </div>
                                   
			
                                    <p class="cart_navigation" >
                                        
                                        <button class="button btn btn-default button-medium" style="float: none; margin-top: 20px;" type="submit" id="trexle_submit">
						<span>
								{l s='Submit Payment' mod='trexle'}
						<i class="icon-chevron-right right"></i>
						</span>
					</button>			
								
                                    </p>			
                                  </div>   
                             
			</form>
                                                    
			
</div>
                                                               
                                                
                