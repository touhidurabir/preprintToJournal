{**
 * plugins/generic/preprintToJournal/templates/settings.tpl
 *
 * Copyright (c) 2023 Simon Fraser University
 * Copyright (c) 2023 John Willinsky
 * Distributed under the GNU GPL v3. For full terms see the file docs/COPYING.
 *
 * Preprint to journal settings tab.
 *}

<script type="text/javascript">
    // Attach the JS file tab handler.
    $(function() {ldelim}
        $('#serviceTabs').pkpHandler('$.pkp.controllers.TabHandler');
    {rdelim});
</script>

<div id="serviceTabs" class="pkp_controllers_tab">
    <ul>
        <li>
            <a 
                name="service" 
                href="{url  router=\PKP\core\PKPApplication::ROUTE_COMPONENT 
                            component="plugins.generic.preprintToJournal.controllers.tab.service.PreprintToJournalServiceTabHandler" 
                            op="create"}"
            >
                {translate key="plugins.generic.preprintToJournal.service.create.name"}
            </a>
        </li>

        <li>
            <a 
                name="serviceList" 
                href="{url  router=\PKP\core\PKPApplication::ROUTE_COMPONENT 
                            component="plugins.generic.preprintToJournal.controllers.tab.service.PreprintToJournalServiceTabHandler" 
                            op="index"}"
            >
                {translate key="plugins.generic.preprintToJournal.service.index.name"}
            </a>
        </li>
    </ul>
</div>
 