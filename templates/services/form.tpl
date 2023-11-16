<script type="text/javascript">

	$(document).ready(function(){ldelim}

		let formElement = $("form#" + '{$formId}')

		$(function() {ldelim}
			formElement.pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
		{rdelim});
		
	{rdelim});
</script>

<form
	class="pkp_form"
	id='{$formId}'
	method="POST"
	action="{url    router=\PKP\core\PKPApplication::ROUTE_COMPONENT 
                    component="plugins.generic.preprintToJournal.controllers.tab.service.PreprintToJournalServiceTabHandler" 
                    op=$op
                    category="generic" 
                    plugin=$pluginName 
                    verb="settings" 
                    save=true
            }"
>
	{csrf}

	{if $op === 'store'}
		{include 
			file="controllers/notification/inPlaceNotification.tpl" 
			notificationId="preprintToJournalServiceFormNotification"
		}
	{/if}

	{if !isset($serviceId)}
		{fbvFormSection}
			<h4>
				{translate key="plugins.generic.preprintToJournal.service.from.add.heading"}
			</h4>
			<p>
				{translate key="plugins.generic.preprintToJournal.service.from.add.description"}
			</p>
		{/fbvFormSection}
	{/if}

	{fbvFormArea id="preprintToJournalServiceFormArea"}

		{fbvFormSection}
			{fbvElement
				type="text"
				id="name"
				label="plugins.generic.preprintToJournal.service.from.field.name"
				required=true
				value=$name
				size=$fbvStyles.size.MEDIUM
			}
		{/fbvFormSection}

        {fbvFormSection}
			{fbvElement
				type="textarea"
				id="description"
				label="plugins.generic.preprintToJournal.service.from.field.description"
				value=$description
				size=$fbvStyles.size.MEDIUM
			}
		{/fbvFormSection}
        
        {fbvFormSection}
			{fbvElement
				type="url"
				id="url"
				label="plugins.generic.preprintToJournal.service.from.field.url"
				required=true
				value=$url
				size=$fbvStyles.size.MEDIUM
			}
		{/fbvFormSection}

        {fbvFormSection}
			{fbvElement
				type="text"
				id="ip"
				label="plugins.generic.preprintToJournal.service.from.field.ip"
				value=$ip
				size=$fbvStyles.size.MEDIUM
			}
		{/fbvFormSection}
	{/fbvFormArea}

	{if isset($serviceId)}
		{fbvElement type="hidden" id="serviceId" value=$serviceId}
	{/if}

	{if $op === 'store'}
		{fbvFormButtons submitText="plugins.generic.preprintToJournal.service.from.add.button.text"}
	{else}
		{fbvFormButtons submitText="plugins.generic.preprintToJournal.service.from.update.button.text"}
	{/if}

	<p><span class="formRequired">{translate key="common.requiredField"}</span></p>
</form>