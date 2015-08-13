<form action="{devblocks_url}{/devblocks_url}" method="post" id="frmDatacenterDomain">
<input type="hidden" name="c" value="datacenter.domains">
<input type="hidden" name="a" value="saveDomainPeek">
<input type="hidden" name="view_id" value="{$view_id}">
{if !empty($model) && !empty($model->id)}<input type="hidden" name="id" value="{$model->id}">{/if}
{if !empty($link_context)}
<input type="hidden" name="link_context" value="{$link_context}">
<input type="hidden" name="link_context_id" value="{$link_context_id}">
{/if}
<input type="hidden" name="do_delete" value="0">
<input type="hidden" name="_csrf_token" value="{$session.csrf_token}">

<fieldset class="peek">
	<legend>{'common.properties'|devblocks_translate}</legend>
	
	<table cellspacing="0" cellpadding="2" border="0" width="98%">
		<tr>
			<td width="1%" nowrap="nowrap"><b>{'common.name'|devblocks_translate}:</b></td>
			<td width="99%">
				<input type="text" name="name" value="{$model->name}" style="width:98%;">
			</td>
		</tr>
		<tr>
			<td width="1%" nowrap="nowrap"><b>{'cerberusweb.datacenter.common.server'|devblocks_translate}:</b></td>
			<td width="99%">
				<select name="server_id">
					<option value="0" {if empty($model->server_id)}selected="selected"{/if}>-- specify server --</option>
					{foreach from=$servers item=server key=server_id}
						<option value="{$server_id}" {if $model->server_id==$server_id}selected="selected"{/if}>{$server->name}</option>
					{/foreach}
				</select>
			</td>
		</tr>
		<tr>
			<td width="1%" nowrap="nowrap"><b>{'common.created'|devblocks_translate|capitalize}:</b></td>
			<td width="99%">
				<input type="text" name="created" class="input_date" size="45" value="{if empty($model->created)}now{else}{$model->created|devblocks_date}{/if}">
			</td>
		</tr>
		<tr>
			<td width="1%" nowrap="nowrap" valign="top"><b>Contacts:</b></td>
			<td width="99%">
				<button type="button" class="chooser_addy"><span class="glyphicons glyphicons-search"></span></button>
				{if !empty($context_addresses)}
				<ul class="chooser-container bubbles">
					{foreach from=$context_addresses item=context_address key=context_address_id}
					<li>{$context_address.a_first_name} {$context_address.a_last_name} &lt;{$context_address.a_email}&gt;<input type="hidden" name="contact_address_id[]" value="{$context_address_id}"><a href="javascript:;" onclick="$(this).parent().remove();"><span class="ui-icon ui-icon-trash" style="display:inline-block;width:14px;height:14px;"></span></a></li>
					{/foreach}
				</ul>
				{/if}
			</td>
		</tr>
		
		{* Watchers *}
		<tr>
			<td width="0%" nowrap="nowrap" valign="top" align="right">{'common.watchers'|devblocks_translate|capitalize}: </td>
			<td width="100%">
				{if empty($model->id)}
					<button type="button" class="chooser_watcher"><span class="glyphicons glyphicons-search"></span></button>
					<ul class="chooser-container bubbles" style="display:block;"></ul>
				{else}
					{$object_watchers = DAO_ContextLink::getContextLinks(CerberusContexts::CONTEXT_DOMAIN, array($model->id), CerberusContexts::CONTEXT_WORKER)}
					{include file="devblocks:cerberusweb.core::internal/watchers/context_follow_button.tpl" context=CerberusContexts::CONTEXT_DOMAIN context_id=$model->id full=true}
				{/if}
			</td>
		</tr>
		
	</table>
</fieldset>

{if !empty($custom_fields)}
<fieldset class="peek">
	<legend>{'common.custom_fields'|devblocks_translate}</legend>
	{include file="devblocks:cerberusweb.core::internal/custom_fields/bulk/form.tpl" bulk=false}
</fieldset>
{/if}

{include file="devblocks:cerberusweb.core::internal/custom_fieldsets/peek_custom_fieldsets.tpl" context=CerberusContexts::CONTEXT_DOMAIN context_id=$model->id}

{* Comments *}
{include file="devblocks:cerberusweb.core::internal/peek/peek_comments_pager.tpl" comments=$comments}

<fieldset class="peek">
	<legend>{'common.comment'|devblocks_translate|capitalize}</legend>
	<textarea name="comment" rows="2" cols="45" style="width:98%;" placeholder="{'comment.notify.at_mention'|devblocks_translate}"></textarea>
</fieldset>

<button type="button" onclick="genericAjaxPopupPostCloseReloadView(null,'frmDatacenterDomain','{$view_id}', false, 'datacenter_domain_save');"><span class="glyphicons glyphicons-circle-ok" style="color:rgb(0,180,0);"></span> {'common.save_changes'|devblocks_translate|capitalize}</button>
{if $model->id && $active_worker->is_superuser}<button type="button" onclick="if(confirm('Permanently delete this domain?')) { this.form.do_delete.value='1';genericAjaxPopupPostCloseReloadView(null,'frmDatacenterDomain','{$view_id}'); } "><span class="glyphicons glyphicons-circle-minus" style="color:rgb(200,0,0);"></span> {'common.delete'|devblocks_translate|capitalize}</button>{/if}

{if !empty($model->id)}
<div style="float:right;">
	<a href="{devblocks_url}c=profiles&type=domain&id={$model->id}-{$model->name|devblocks_permalink}{/devblocks_url}">view full record</a>
</div>
<br clear="all">
{/if}
</form>

<script type="text/javascript">
	var $popup = genericAjaxPopupFetch('peek');
	
	$popup.one('popup_open', function(event,ui) {
		var $textarea = $(this).find('textarea[name=comment]');
		
		$(this).find('input[type=text]:first').focus();
		
		$(this).dialog('option','title',"{'cerberusweb.datacenter.domain'|devblocks_translate|escape:'javascript' nofilter}");
		
		$(this).find('button.chooser_watcher').each(function() {
			ajax.chooser(this,'cerberusweb.contexts.worker','add_watcher_ids', { autocomplete:true });
		});
		
		$(this).find('input.input_date').cerbDateInputHelper();
		
		$('#frmDatacenterDomain button.chooser_addy').each(function() {
			ajax.chooser(this,'cerberusweb.contexts.address','contact_address_id', { autocomplete:true });
		});
		
		// Form hints
		
		$textarea
			.focusin(function() {
				$(this).siblings('div.cerb-form-hint').fadeIn();
			})
			.focusout(function() {
				$(this).siblings('div.cerb-form-hint').fadeOut();
			})
			;
		
		// @mentions
		
		var atwho_workers = {CerberusApplication::getAtMentionsWorkerDictionaryJson() nofilter};

		$textarea.atwho({
			at: '@',
			{literal}displayTpl: '<li>${name} <small style="margin-left:10px;">${title}</small> <small style="margin-left:10px;">@${at_mention}</small></li>',{/literal}
			{literal}insertTpl: '@${at_mention}',{/literal}
			data: atwho_workers,
			searchKey: '_index',
			limit: 10
		});
	} );
</script>
