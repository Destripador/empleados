import Vue from 'vue'
import App from './views/App.vue'
Vue.mixin({ methods: { t, n } })

const View = Vue.extend(App)
new View().$mount('#content')
