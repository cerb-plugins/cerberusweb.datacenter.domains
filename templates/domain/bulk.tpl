<form action="{devblocks_url}{/devblocks_url}" method="POST" id="formBatchUpdate" name="formBatchUpdate" onsubmit="return false;">
<input type="hidden" name="c" value="profiles">
<input type="hidden" name="a" value="handleSectionAction">
<input type="hidden" name="section" value="domain">
<input type="hidden" name="action" value="startBulkUpdateJson">
<input type="hidden" name="view_id" value="{$view_id}">
<input type="hidden" name="ids" value="{$ids}">
<input type="hidden" name="_csrf_token" value="{$session.csrf_token}">

<fieldset class="peek">
	<legend>{'common.bulk_update.with'|devblocks_translate|capitalize}</legend>
	<label><input type="radio" name="filter" value="" {if empty($ids)}checked{/if}> {'common.bulk_update.filter.all'|devblocks_translate}</label> 
 	{if !empty($ids)}
		<label><input type="radio" name="filter" value="checks" {if !empty($ids)}checked{/if}> {'common.bulk_update.filter.checked'|devblocks_translate}</label> 
	{else}
		<label><input type="radio" name="filter" value="sample"> {'common.bulk_update.filter.random'|devblocks_translate} </label><input type="text" name="filter_sample_size" size="5" maxlength="4" value="100" class="input_number">
	{/if}
</fieldset>

<fieldset class="peek">
	<legend>Set Fields</legend>
	<table cellspacing="0" cellpadding="2" width="100%">
		<tr>
			<td width="0%" nowrap="nowrap" valign="top" align="right">{'common.status'|devblocks_translate|capitalize}:</td>
			<td width="100%"><select name="status">
				<option value=""></option>
				{if $active_worker->hasPriv('datacenter.domains.actions.delete')}
				<option value="deleted">{'status.deleted'|devblocks_translate|capitalize}</option>
				{/if}
			</select>
			{if $active_worker->hasPriv('datacenter.domains.actions.delete')}
			<button type="button" onclick="this.form.status.selectedIndex = 1;">{'status.deleted'|devblocks_translate|lower}</button>
			{/if}
			</td>
		</tr>
		
		{if is_array($servers) && !empty($servers)}
		<tr>
			<td width="0%" nowrap="nowrap" valign="top" align="right">{'dao.datacenter_domain.server_id'|devblocks_translate|capitalize}:</td>
			<td width="100%"><select name="server_id">
				<option value=""></option>
				{foreach from=$servers item=server}
				<option value="{$server->id}">{$server->name}</option>
				{/foreach}
			</select>
			</td>
		</tr>
		{/if}
		
	</table>
</fieldset>

{if !empty($custom_fields)}
<fieldset class="peek">
	<legend>Set Custom Fields</legend>
	{include file="devblocks:cerberusweb.core::internal/custom_fields/bulk/form.tpl" bulk=true}	
</fieldset>
{/if}

{include file="devblocks:cerberusweb.core::internal/custom_fieldsets/peek_custom_fieldsets.tpl" context=CerberusContexts::CONTEXT_DOMAIN bulk=true}

{include file="devblocks:cerberusweb.core::internal/macros/behavior/bulk.tpl" macros=$macros}

<fieldset class="peek">
	<legend>Actions</legend>

	<label><input type="checkbox" name="do_broadcast" id="chkMassReply" onclick="$('#bulkDatacenterDomainBroadcast').toggle();"> Send Broadcast</label>
	<input type="hidden" name="broadcast_format" value="">
	
	<blockquote id="bulkDatacenterDomainBroadcast" style="display:none;margin:10px;">
		<b>From:</b>
		
		<div style="margin:0px 0px 5px 10px;">
			<select name="broadcast_group_id">
				{foreach from=$groups item=group key=group_id}
				{if $active_worker_memberships.$group_id}
				<option value="{$group->id}">{$group->name}</option>
				{/if}
				{/foreach}
			</select>
		</div>
		
		<b>Subject:</b>
		
		<div style="margin:0px 0px 5px 10px;">
			<input type="text" name="broadcast_subject" value="" style="width:100%;border:1px solid rgb(180,180,180);padding:2px;">
		</div>
		
		<b>Compose:</b>
		
		<div style="margin:0px 0px 5px 10px;">
			<textarea name="broadcast_message" style="width:100%;height:200px;"></textarea>
			
			<div>
				<button type="button" class="cerb-popupmenu-trigger" onclick="">Insert placeholder &#x25be;</button>
				<button type="button" onclick="ajax.chooserSnippet('snippets',$('#bulkDatacenterDomainBroadcast textarea[name=broadcast_message]'), { 'cerberusweb.contexts.datacenter.domain':'', '{CerberusContexts::CONTEXT_WORKER}':'{$active_worker->id}' });">{'common.snippets'|devblocks_translate|capitalize}</button>
				
				{$types = $values._types}
				{function tree level=0}
					{foreach from=$keys item=data key=idx}
						{if is_array($data)}
							<li>
								<div>{$idx|capitalize}</div>
								<ul>
									{tree keys=$data level=$level+1}
								</ul>
							</li>
						{else}
							{$type = $types.{$data->key}}
							<li data-token="{$data->key}{if $type == Model_CustomField::TYPE_DATE}|date{/if}" data-label="{$data->label}"><div style="font-weight:bold;">{$data->l|capitalize}</div></li>
						{/if}
					{/foreach}
				{/function}
				
				<ul class="menu" style="width:150px;">
				{tree keys=$placeholders}
				</ul>
			</div>
		</div>
		
		<b>{'common.attachments'|devblocks_translate|capitalize}:</b>
		<div style="margin:0px 0px 5px 10px;">
			<button type="button" class="chooser_file"><span class="glyphicons glyphicons-paperclip"></span></button>
			<ul class="bubbles chooser-container">
		</div>
		
		<b>{'common.options'|devblocks_translate|capitalize}:</b>
		<div style="margin:0px 0px 5px 10px;">
			<label><input type="radio" name="broadcast_is_queued" value="0" checked="checked"> Save as drafts</label>
			<label><input type="radio" name="broadcast_is_queued" value="1"> Send now</label>
		</div>
		
		<b>{'common.status'|devblocks_translate|capitalize}:</b>
		<div style="margin:0px 0px 5px 10px;">
			<label><input type="radio" name="broadcast_status_id" value="{Model_Ticket::STATUS_OPEN}"> {'status.open'|devblocks_translate|capitalize}</label>
			<label><input type="radio" name="broadcast_status_id" value="{Model_Ticket::STATUS_WAITING}" checked="checked"> {'status.waiting'|devblocks_translate|capitalize}</label>
			<label><input type="radio" name="broadcast_status_id" value="{Model_Ticket::STATUS_CLOSED}"> {'status.closed'|devblocks_translate|capitalize}</label>
		</div>
	</blockquote>

</fieldset>

<button type="button" class="submit"><span class="glyphicons glyphicons-circle-ok" style="color:rgb(0,180,0);"></span> {'common.save_changes'|devblocks_translate|capitalize}</button>
</form>

<script type="text/javascript">
$(function() {
	var $frm = $('#formBatchUpdate');
	var $popup = genericAjaxPopupFind($frm);
	
	$popup.one('popup_open', function(event,ui) {
		$popup.dialog('option','title',"{'common.bulk_update'|devblocks_translate|capitalize|escape:'javascript' nofilter}");
		$popup.css('overflow', 'inherit');
		
		$popup.find('button.submit').click(function() {
			genericAjaxPost('formBatchUpdate', '', null, function(json) {
				if(json.cursor) {
					// Pull the cursor
					var $tips = $('#{$view_id}_tips').html('');
					var $spinner = $('<span class="cerb-ajax-spinner"/>').appendTo($tips);
					genericAjaxGet($tips, 'c=internal&a=viewBulkUpdateWithCursor&view_id={$view_id}&cursor=' + json.cursor);
				}
				
				genericAjaxPopupClose($popup);
			});
		});
		
		{include file="devblocks:cerberusweb.core::internal/views/bulk_broadcast_jquery.tpl"}
	});
});
</script>