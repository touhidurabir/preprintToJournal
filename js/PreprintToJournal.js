pkp.registry.registerComponent('preprint-to-journal', {
	name: 'PreprintToJournal',
    props: {
        formJournalSelection: Object,
    },
    template: `
    <div>
        <pkp-form
            @set="set" 
            @success="onSuccess"
            v-if="showFormJournalSelection"
            v-bind="formJournalSelection"    
        />
        <div v-else>
            <pkp-form
                @set="set" 
                v-bind="formJournalSubmission"    
            />
        </div>
    </div>
  `,
  data() {
    return { 
        textToShow: 'Here you can put another form instead',
        showFormJournalSelection: true,
        formJournalSubmission: null,
    }
  },
  methods: {
    set: function (key, data) {
        this.$emit('set', key, data)
    },
    onSuccess: function(response) {
        // this.textToShow = `Response : ${response.message}`;
        // console.log(response.data.form_component);
        
        this.formJournalSubmission = response.data.form_component;
        this.showFormJournalSelection = false;
    },
  }
});
