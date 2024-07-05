import Vue from 'vue'
import App from './views/Settings/Settings.vue'
Vue.mixin({ methods: { t, n } })

const View = Vue.extend(App)
new View().$mount('#admin')
