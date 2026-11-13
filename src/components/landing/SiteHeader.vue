<script setup>
import { computed, ref, onMounted, onBeforeUnmount } from 'vue';
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
const brandLogo = '/images/put-bez-pregrad/favicon.svg';
const mobileMenuOpen = ref(false);
const headerRoot = ref(null);
const closeMenu = () => { mobileMenuOpen.value = false; };
function closeMenuOnOutsideClick(event) {
  if (mobileMenuOpen.value && !headerRoot.value?.contains(event.target)) closeMenu();
}
onMounted(() => document.addEventListener('click', closeMenuOnOutsideClick));
onBeforeUnmount(() => document.removeEventListener('click', closeMenuOnOutsideClick));
</script>

<template>
<header ref="headerRoot" class="site-header">
      <div class="site-header__inner">
        <button
          type="button"
          class="transport-menu-toggle"
          :aria-label="mobileMenuOpen ? 'Закрыть меню' : 'Открыть меню'"
          :aria-expanded="mobileMenuOpen"
          aria-controls="landing-navigation"
          @click="mobileMenuOpen = !mobileMenuOpen"
        >
          <svg viewBox="0 0 24 24" width="23" height="23" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
            <path v-if="mobileMenuOpen" d="M5 5 19 19M19 5 5 19" />
            <path v-else d="M4 7h16M4 12h16M4 17h16" />
          </svg>
          <span>{{ mobileMenuOpen ? 'Закрыть' : 'Меню' }}</span>
        </button>
        <a href="#top" class="logo" aria-label="Путь без преград — наверх">
          <img class="brand-symbol" :src="brandLogo" width="74" height="74" alt="" />
          <span class="brand-wordmark" aria-hidden="true">
            <span>Путь</span>
            <span><span class="brand-wordmark__accent">без</span> преград</span>
          </span>
        </a>
        <nav
          id="landing-navigation"
          class="header-nav"
          :class="{ 'header-nav--open': mobileMenuOpen }"
          aria-label="Разделы страницы"
          @keydown.esc="closeMenu"
        >
          <a href="#benefits" @click="closeMenu">Преимущества</a>
          <a href="#destinations" @click="closeMenu">С сопровождением</a>
          <a href="#fleet" @click="closeMenu">Автопарк</a>
          <a href="#pricing" @click="closeMenu">Цены</a>
          <a href="#request" @click="closeMenu">Как заказать</a>
        </nav>
        <div class="header-contacts">
          <div class="header-phones">
            <a :href="primaryPhoneHref">{{ primaryPhone }}</a>
            <a :href="secondaryPhoneHref">{{ secondaryPhone }}</a>
          </div>
          <a class="header-email" :href="'mailto:' + email">{{ email }}</a>
          <a class="max-link" :href="maxUrl" target="_blank" rel="noopener noreferrer" aria-label="Написать в MAX"><span class="max-icon"><MaxIcon /></span></a>
        </div>
      </div>
    </header>
</template>
