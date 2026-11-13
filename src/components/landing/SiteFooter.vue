<script setup>
import { computed } from 'vue';
import MaxIcon from '../MaxIcon.vue';
import { telHref } from '../../utils/contactInput.js';

const props = defineProps({
  settings: { type: Object, required: true },
});
const primaryPhone = computed(() => props.settings.primary_phone);
const secondaryPhone = computed(() => props.settings.secondary_phone);
const email = computed(() => props.settings.contact_email);
const maxUrl = computed(() => props.settings.max_url);
const primaryPhoneHref = computed(() => telHref(primaryPhone.value));
const secondaryPhoneHref = computed(() => telHref(secondaryPhone.value));
const brandLogoLight = '/images/put-bez-pregrad/brand-symbol-light.svg';
</script>

<template>
<footer class="site-footer">
      <div class="container site-footer__inner">
        <div class="footer-brand">
          <a class="footer-brand__link" href="#top" aria-label="Путь без преград — наверх">
            <img class="footer-brand__symbol" :src="brandLogoLight" width="86" height="86" alt="" />
            <span class="footer-brand__name" aria-hidden="true">
              <span>Путь</span>
              <span><span class="footer-brand__accent">без</span> преград</span>
            </span>
          </a>
          <p>Перевозка людей на инвалидных колясках<br>Ульяновск и Ульяновская область</p>
        </div>
        <nav aria-label="Навигация в подвале"><a href="#benefits">Преимущества</a><a href="#destinations">С сопровождением</a><a href="#fleet">Автопарк</a><a href="#pricing">Стоимость</a><a href="#request">Контакты</a></nav>
        <div class="footer-contact"><a :href="primaryPhoneHref">{{ primaryPhone }}</a><a :href="secondaryPhoneHref">{{ secondaryPhone }}</a><a :href="'mailto:' + email">{{ email }}</a></div>
        <a class="max-link" :href="maxUrl" target="_blank" rel="noopener noreferrer" aria-label="Написать в MAX"><span class="max-icon"><MaxIcon /></span></a>
      </div>
    </footer>
</template>
