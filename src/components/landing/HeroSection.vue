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
const photoVan = '/images/accessible-van.avif';
const primaryPieces = computed(() => {
  const match = primaryPhone.value.match(/^(\+7\s*\(\d{3,4}\))\s*(.*)$/);
  return match ? [match[1], match[2]] : ['', primaryPhone.value];
});
</script>

<template>
<section class="hero" aria-labelledby="hero-title">
        <div class="hero__image" aria-hidden="true">
          <img :src="photoVan" alt="" width="1200" height="900" fetchpriority="high" decoding="async" />
        </div>
        <div class="hero__content container">
          <div class="hero__text">
            <p class="eyebrow">УЛЬЯНОВСК И УЛЬЯНОВСКАЯ ОБЛАСТЬ · 24/7</p>
            <h1 id="hero-title">Перевозка инвалидов-<em>колясочников</em> в спецавтомобиле по городу и области</h1>
            <div class="hero__phone-panel">
              <span class="hero__phone-label">Позвоните и закажите поездку</span>
              <a :href="primaryPhoneHref" class="hero__phone" :aria-label="'Позвонить на номер ' + primaryPhone">
                <span v-if="primaryPieces[0]" class="hero__phone-prefix">{{ primaryPieces[0] }}</span>
                <strong>{{ primaryPieces[1] }}</strong>
              </a>
              <a :href="secondaryPhoneHref" class="hero__secondary">{{ secondaryPhone }}</a>
            </div>
            <div class="hero__actions">
              <a :href="primaryPhoneHref" class="cta cta--red"><span aria-hidden="true">☎</span> Позвонить сейчас</a>
              <a :href="maxUrl" target="_blank" rel="noopener noreferrer" class="cta cta--white" aria-label="Написать в MAX"><span class="max-icon"><MaxIcon /></span> Написать</a>
              <a href="#request" class="cta cta--outline">Оставить заявку</a>
            </div>
          </div>
          <div class="hero__stamp" aria-label="Круглосуточная связь"><strong>24/7</strong><span>На связи<br>каждый день</span></div>
        </div>
      </section>
</template>
