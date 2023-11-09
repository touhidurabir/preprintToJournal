{capture assign=preprintToJournalServiceListUrl}
    {url 
        router=\PKP\core\PKPApplication::ROUTE_COMPONENT 
        component="plugins.generic.preprintToJournal.controllers.tab.service.PreprintToJournalServiceGridHandler" 
        op="fetchGrid" 
        escape=false
    }
{/capture}

{load_url_in_div 
    id="preprintToJournalServiceGridContainer" 
    url=$preprintToJournalServiceListUrl
}
