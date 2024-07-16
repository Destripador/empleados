import Vue from 'vue'
import App from './views/components/ContactList.vue'
Vue.mixin({ methods: { t, n } })

const View = Vue.extend(App)
new View().$mount('#contactlist')
