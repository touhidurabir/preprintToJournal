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
    return { 
        textToShow: 'Here you can put another form instead',
        showFormJournalPublication: true
    }
  },
  methods: {
    set: function (key, data) {
        this.$emit('set', key, data)
    },
    onSuccess: function(response) {
        this.showFormJournalPublication = false;
        
        // this.textToShow = `Data from first form - url: ${data.publishingJournalUrl}, key:${data.apiKey}`
        this.textToShow = `Response from API : ${response.data.message} with return http status code : ${response.status}`;
    },
  }
});
