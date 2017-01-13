<div class="hikashop_zarinpal_end" id="hikashop_zarinpal_end">
	<span id="hikashop_zarinpal_end_message" class="hikashop_zarinpal_end_message">
		<?php echo JText::sprintf('PLEASE_WAIT_BEFORE_REDIRECTION_TO_X',$this->payment_name).'<br/>'. JText::_('CLICK_ON_BUTTON_IF_NOT_REDIRECTED');?> 
	</span>
	<span id="hikashop_zarinpal_end_spinner" class="hikashop_zarinpal_end_spinner">
		<img src="<?php echo HIKASHOP_IMAGES.'spinner.gif';?>" />
	</span>
	<br/>
	<form id="hikashop_zarinpal_form" name="hikashop_zarinpal_form" action="<?php echo $this->vars['zarinpal']; ?>" method="post"> 
		<div id="hikashop_zarinpal_end_image" class="hikashop_zarinpal_end_image">
			<input id="hikashop_zarinpal_button" type="submit" class="btn btn-primary" value="<?php echo JText::_('PAY_NOW');?>" name="" alt="<?php echo JText::_('PAY_NOW');?>" onclick="document.getElementById('hikashop_zarinpal_form').submit(); return false;"/>
		</div>
	</form>
	<script type="text/javascript">	
		document.getElementById('hikashop_zarinpal_form').submit();	
	</script>
</div>