pkp.registry.registerComponent('preprint-to-journal', {
	name: 'PreprintToJournal',
    props: {
        formJournalSelection: Object
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
            {{textToShow}}
        </div>
    </div>
  `,
  data() {
    return { 
        textToShow: 'Here you can put another form instead',
        showFormJournalSelection: true
    }
  },
  methods: {
    set: function (key, data) {
        this.$emit('set', key, data)
    },
    onSuccess: function(response) {
        this.showFormJournalSelection = false;
        this.textToShow = `Response : ${response.message}`;
    },
  }
});
