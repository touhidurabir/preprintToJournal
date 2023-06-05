<tab 
    id="journal-publication" 
    label="Journal Publication"
>
    <pkp-form 
        v-bind="components.{$smarty.const.FORM_JOURNAL_PUBLICATION}" 
        @set="set" 
        action="{{$journalPublishingUrl}}"
    />
</tab>