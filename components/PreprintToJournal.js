pkp.Vue.component('preprint-to-journal', {
	name: 'PreprintToJournal',
    props: {
        formJournalPublication: Object
    },
    template: `
    <div>
        <pkp-form
            @set="set" 
            @success="onSuccess"
            v-if="showFormJournalPublication"
            v-bind="formJournalPublication"    

        />
        <div v-else>
            {{textToShow}}
        </div>
    </div>
  `,
  data() {
    console.log(this.formJournalPublication)
    return { 
        forms: {[this.formJournalPublication.id]: this.formJournalPublication},
        textToShow: 'Here you can put another form instead',
        showFormJournalPublication: true
    }
  },
  methods: {
    set: function (key, data) {
        this.$emit('set', key, data)
    },
    onSuccess: function(data) {
        this.showFormJournalPublication = false;
        this.textToShow = `Data from first form - url: ${data.publishingJournalUrl}, key:${data.apiKey}`
    }
  }
});

