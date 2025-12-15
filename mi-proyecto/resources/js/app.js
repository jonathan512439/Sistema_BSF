// resources/js/app.js

import './bootstrap';
import { createApp } from 'vue';
import App from './components/App.vue';
import router from './router'
const app = createApp(App);

// si tienes componentes base globales (BaseButton, etc.), se registran aquí
// app.component('BaseButton', BaseButton);
app.use(router)
app.mount('#app');
