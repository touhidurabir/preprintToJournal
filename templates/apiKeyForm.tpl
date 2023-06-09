{**
 * templates/user/apiProfileForm.tpl
 *
 * Copyright (c) 2014-2021 Simon Fraser University
 * Copyright (c) 2003-2021 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Public user profile form.
 *}

<script>
	$(function() {ldelim}
		// Attach the form handler.
		$('#preprintToJournalApiProfileForm')
			.pkpHandler('$.pkp.controllers.form.AjaxFormHandler');
	{rdelim});
</script>

<form 
	class="pkp_form" 
	id="preprintToJournalApiProfileForm" 
	method="post" 
	action="{url op="savePreprintToJournalApiProfile"}" 
	enctype="multipart/form-data"
>
	
	{* Help Link *}
	{help file="user-profile" class="pkp_help_tab"}

	{csrf}

	{include file="controllers/notification/inPlaceNotification.tpl" notificationId="apiProfileNotification"}

	{fbvFormSection title="plugins.generic.preprintToJournal.user.profile.apiKey.form.title"}
		{if !$apiKey}
			{assign var=apiKey value="common.none"|translate}
		{/if}
		{fbvElement id=apiKey type="text" readonly="true" inline=true value=$apiKey size=$fbvStyles.size.MEDIUM}
		{if !$apiSecretMissing}
			{fbvElement id=apiKeyAction type="hidden" readonly="true" value=$apiKeyAction}
			<button
				type="submit"
				{if $apiKeyAction === \APP\plugins\generic\preprintToJournal\controllers\tab\user\form\CustomApiProfileForm::API_KEY_DELETE}
					onClick="return confirm({translate|json_encode|escape key='user.apiKey.remove.confirmation.message'})"
					class="pkpButton pkpButton--isWarnable"
				{else}
					class="pkp_button pkp_button_primary"
				{/if}
			>
				{translate key=$apiKeyActionTextKey}
			</button>
		{/if}
		<p class="pkp_helpers_align_left pkp_helpers_full">
			{translate key=($apiKeyAction === \APP\plugins\generic\preprintToJournal\controllers\tab\user\form\CustomApiProfileForm::API_KEY_NEW) ? "user.apiKey.generateWarning" : "user.apiKey.removeWarning"}
		</p>
	{/fbvFormSection}

	{fbvFormSection title="plugins.generic.preprintToJournal.user.profile.apiKey.form.journalPath.title"}
		{fbvElement id=journalPath type="text" readonly="true" inline=true value=$journalPath size=$fbvStyles.size.MEDIUM}
	{/fbvFormSection}

	<p>
		{capture assign="privacyUrl"}
			{url router=\PKP\core\PKPApplication::ROUTE_PAGE page="about" op="privacy"}
		{/capture}
		{translate key="user.privacyLink" privacyUrl=$privacyUrl}
	</p>
</form>
