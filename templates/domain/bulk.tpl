<form action="{devblocks_url}{/devblocks_url}" method="POST" id="formBatchUpdate" name="formBatchUpdate" onsubmit="return false;">
<input type="hidden" name="c" value="datacenter.domains">
<input type="hidden" name="a" value="doDomainBulkUpdate">
<input type="hidden" name="view_id" value="{$view_id}">
<input type="hidden" name="ids" value="{$ids}">

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
	{*if $active_worker->hasPriv('crm.opp.view.actions.broadcast')*}

	<label><input type="checkbox" name="do_broadcast" id="chkMassReply" onclick="$('#bulkDatacenterDomainBroadcast').toggle();">Send Broadcast</label>
	<blockquote id="bulkDatacenterDomainBroadcast" style="display:none;margin:10px;">
		<b>From:</b> <br>
		<select name="broadcast_group_id">
			{foreach from=$groups item=group key=group_id}
			{if $active_worker_memberships.$group_id}
			<option value="{$group->id}">{$group->name}</option>
			{/if}
			{/foreach}
		</select>
		<br>
		<b>Subject:</b> <br>
		<input type="text" name="broadcast_subject" value="" style="width:100%;border:1px solid rgb(180,180,180);padding:2px;"><br>
		<b>Compose:</b> {*[<a href="#">syntax</a>]*}<br>
		<textarea name="broadcast_message" style="width:100%;height:200px;border:1px solid rgb(180,180,180);padding:2px;"></textarea>
		<br>
		<button type="button" onclick="ajax.chooserSnippet('snippets',$('#bulkDatacenterDomainBroadcast textarea[name=broadcast_message]'), { 'cerberusweb.contexts.datacenter.domain':'', '{CerberusContexts::CONTEXT_WORKER}':'{$active_worker->id}' });">{'common.snippets'|devblocks_translate|capitalize}</button>
		<button type="button" onclick="genericAjaxPost('formBatchUpdate','bulkDatacenterDomainBroadcastTest','c=datacenter.domains&a=doBulkUpdateBroadcastTest');"><span class="cerb-sprite2 sprite-gear"></span> Test</button><!--
		--><select class="insert-placeholders">
			<option value="">-- insert at cursor --</option>
			{foreach from=$token_labels key=k item=v}
			<option value="{literal}{{{/literal}{$k}{literal}}}{/literal}">{$v}</option>
			{/foreach}
		</select>
		<br>
		<div id="bulkDatacenterDomainBroadcastTest"></div>
		<b>{'common.options'|devblocks_translate|capitalize}:</b> 
		<label><input type="radio" name="broadcast_is_queued" value="0" checked="checked"> Save as drafts</label>
		<label><input type="radio" name="broadcast_is_queued" value="1"> Send now</label>
		<br>
		<b>{'common.status'|devblocks_translate|capitalize}:</b> 
		<label><input type="radio" name="broadcast_next_is_closed" value="0"> {'status.open'|devblocks_translate|capitalize}</label>
		<label><input type="radio" name="broadcast_next_is_closed" value="2" checked="checked"> {'status.waiting'|devblocks_translate|capitalize}</label>
		<label><input type="radio" name="broadcast_next_is_closed" value="1"> {'status.closed'|devblocks_translate|capitalize}</label>
	</blockquote><br>
	{*/if*}
</fieldset>

<button type="button" onclick="genericAjaxPopupClose('peek');genericAjaxPost('formBatchUpdate','view{$view_id}');"><span class="cerb-sprite2 sprite-tick-circle"></span> {'common.save_changes'|devblocks_translate|capitalize}</button>
</form>

<script type="text/javascript">
	$popup = genericAjaxPopupFetch('peek');
	$popup.one('popup_open', function(event,ui) {
		var $this = $(this);
		$this.dialog('option','title',"{'common.bulk_update'|devblocks_translate|capitalize}");
		
		$this.find('select.insert-placeholders').change(function(e) {
			var $select = $(this);
			var $val = $select.val();
			
			if($val.length == 0)
				return;
			
			var $textarea = $select.siblings('textarea[name=broadcast_message]');
			
			$textarea.insertAtCursor($val).focus();
			
			$select.val('');
		});
	});
</script>
