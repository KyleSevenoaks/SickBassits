<?php
class SGCachePressView extends SGCachePress
{
	# Render the Settings page
	public function settingsPage()
	{
		if(!current_user_can('manage_options'))
			wp_die('You do not have sufficient permissions to access this page.');

	echo"
	<style>
	.metabox-holder{width:30%;display:inline-table;margin-right:2.5%;}
	.FullScreen{width:95.5%;display:block;margin-right:0;}
	.hndle{cursor:default!important;}
	select{min-width:30%;position:absolute;right:10px;}
	label{line-height:22px;font-style:normal;}
	.BlockControls{text-align:right;margin-bottom:0;}
	</style>
	<div id='icon-plugins' class='icon32'><br/></div>
	<h2>SG Cache :: Settings</h2>";

	echo"
	<div class='wrap'>
		<div class='metabox-holder FullScreen'>
			<div class='postbox'>
				<h3 class='hndle'><span>Cache Settings</span></h3>
				<div class='inside'>
					<div>
						<p id='menu-item-wrap'>
							<label for='SGCP_Use_SG_Cache' class='howto'><span>Enable SiteGround cache</span>
								<select name='SGCP_Use_SG_Cache' id='SGCP_Use_SG_Cache'>
									<option value='1' ".($this->options['SGCP_Use_SG_Cache']=='1'?'selected':'').">Yes</option>
									<option value='0' ".($this->options['SGCP_Use_SG_Cache']=='0'?'selected':'').">No</option>
								</select>
							</label>
						</p>
						<p id='menu-item-wrap'>
							<label for='SGCP_Autoflush' class='howto'><span>Autoflush cache</span>
								<select name='SGCP_Autoflush' id='SGCP_Autoflush'>
									<option value='1' ".($this->options['SGCP_Autoflush']=='1'?'selected':'').">Yes</option>
									<option value='0' ".($this->options['SGCP_Autoflush']=='0'?'selected':'').">No</option>
								</select>
							</label>
						</p>
						<p class='BlockControls'>
							<span>
								<img id='AjaxLoading_Themes' style='vertical-align:middle' class='ajax-feedback' src='".esc_url(admin_url('images/wpspin_light.gif'))."' />
								<input type='submit' id='SGCP_SaveSettings' name='SGCP_SaveSettings' value='Save' class='button-secondary' />
							</span>
						</p>
					</div>
				</div>
			</div>
		</div>
	</div>";
}

# Render the Purge Cache page
public function purgeCachePage()
{
	if(!current_user_can('manage_options'))
		wp_die('You do not have sufficient permissions to access this page.');

	echo"
	<div id='icon-plugins' class='icon32'><br/></div>
	<h2>Purge SG Cache</h2>
	<div class='updated below-h2' id='message' style='margin:20px 20px 0 0;'>
	<p>If you purge the SG Cache storage the entire cache of the website will have to be regenerated.<br>Including, but not limited to:
	<ul>
	<li>&#183; Pages</li>
	<li>&#183; Style Sheets (CSS)</li>
	<li>&#183; Image files</li>
	<li>&#183; JavaScript files</li>
	</ul>
	</p>
	</div>
	<div class='alignleft' style='margin-top:25px;'>
	<input type='submit' value='Purge SG Cache Completely' class='button-primary widget-control-save' id='SGCachePurgeNow' name='SGCachePurgeNow' style='width:200px;'>
	<img id='AjaxLoading' style='vertical-align:middle' class='ajax-feedback' src='".esc_url(admin_url('images/wpspin_light.gif'))."'></div>";
}

public function loadJS()
{
	echo <<<HTML
<script type="text/javascript">
jQuery(document).ready(function($){

// Monitor the "Purge Now" button and execute an AJAX call
  jQuery('input#SGCachePurgeNow').live('click',function(){
      jQuery('#SGCachePurgeNow').attr('disabled','disabled').attr('value','Purging! Please wait ...');
      jQuery('img#AjaxLoading').css({"visibility":"visible"});
      jQuery.post(ajaxurl,{
	  action: 'PurgeSGCacheNow',
	  objects: 'all'
	},function(response){
	  setTimeout("jQuery('#SGCachePurgeNow').removeAttr('disabled').attr('value','Purge SG Cache Completely')",2000);
	  jQuery('img#AjaxLoading').css({"visibility":"hidden"});
	  if(response=="purged!"){alert('SG Cache successfully purged!');}
	  else{alert(response);}
      });
   });

// Themes
jQuery('input#SGCP_SaveSettings').live('click',function(){jQuery.SGCPJS_SaveSettings('SGCP_SaveSettings','AjaxLoading_General',{'SGCP_Use_SG_Cache':jQuery("#SGCP_Use_SG_Cache").val(),'SGCP_Autoflush':jQuery("#SGCP_Autoflush").val(),'SGCP_Config_Chages':jQuery("#SGCP_Config_Chages").val()});});

jQuery.SGCPJS_SaveSettings=function(input,image,args){
  jQuery('#'+input).attr('disabled','disabled').attr('value','Saving ...');
  jQuery('img#'+image).css({"visibility":"visible"});
  jQuery.post(ajaxurl,{
      action:'SGCPSaveSettings',
      'options[]':args
    },function(response){
      if(response=="Saved!"){alert('Settings were successfully saved!');}
      else{alert('Failed saving the changes: '+response);}
      jQuery('#'+input).removeAttr('disabled').attr('value','Save');
      jQuery('img#'+image).css({"visibility":"hidden"});
  });
};

});
</script>
HTML;
}

}