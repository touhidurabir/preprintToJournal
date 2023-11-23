pkp.registry.registerComponent('preprint-to-journal', {
	name: 'PreprintToJournal',
    props: {
        formJournalSelection: Object,
    },
    template: `
    <div>
        <pkp-form
            @set="set"
            @success="onSuccessJournalSelection"
            @error="onErrorJournalSelection"
            v-if="showFormJournalSelection"
            v-bind="formJournalSelection" 
        />
        <div v-else>
            <pkp-form
                @set="set" 
                @success="onSuccessJournalSubmission"
                @error="onErrorJournalSubmission"
                v-if="showFormJournalSubmission"
                v-bind="formJournalSubmission"
            />
            <span v-else>{{textToShow}}</span>
        </div>
    </div>
  `,
  data() {
    return { 
        textToShow: 'Here you can put another form instead',
        showFormJournalSelection: true,
        showFormJournalSubmission: false,
        formJournalSubmission: null,
    }
  },
  methods: {
    set: function (key, data) {
        this.$emit('set', key, data)
    },
    onErrorJournalSelection: function(error) {
        this.textToShow = `Error : ${error.message}`;
    },
    onSuccessJournalSelection: function(response) {
        this.formJournalSubmission = response.data.form_component;
        this.showFormJournalSelection = false;
        this.showFormJournalSubmission = true;
    },
    onErrorJournalSubmission: function(error) {

    },
    onSuccessJournalSubmission: function(response) {
        console.log(response);
    },
  }
});
