<script setup>
import TransportIcon from './components/TransportIcon.vue';
import MaxIcon from './components/MaxIcon.vue';
import { ref, computed, onMounted } from 'vue';
import { useTransportRequest } from './composables/useTransportRequest.js';
import { formatRuPhone, telHref, normalizeEmail, applyPhoneInput, phoneFocus, phoneBlur } from './utils/contactInput.js';

const settings = ref({
  primary_phone: '+7 (8422) 71-73-03',
  secondary_phone: '+7 (967) 771-73-03',
  contact_email: 'bezpregrad73@yandex.ru',
  max_url: 'https://max.ru/u/f9LHodD0cOIMqlrwFHrhALk5VeAh-knTiBJCLnhZSFM3iMksBuOEXJ6JMec',
});
const primaryPhone = computed(() => settings.value.primary_phone);
const secondaryPhone = computed(() => settings.value.secondary_phone);
const email = computed(() => settings.value.contact_email);
const maxUrl = computed(() => settings.value.max_url);
const primaryPhoneHref = computed(() => telHref(primaryPhone.value));
const secondaryPhoneHref = computed(() => telHref(secondaryPhone.value));
const primaryPieces = computed(() => {
  const match = primaryPhone.value.match(/^(\+7\s*\(\d{3,4}\))\s*(.*)$/);
  return match ? [match[1], match[2]] : ['', primaryPhone.value];
});
const brandLogo = '/images/put-bez-pregrad/favicon.svg';
const brandLogoLight = '/images/put-bez-pregrad/brand-symbol-light.svg';
const photoVan = '/images/accessible-van.avif';
const photoPassenger = '/images/accessible-passenger.avif';
const photoBoarding = '/images/accessible-boarding.avif';
const {
  departure, destination, passenger, contact, website,
  consent, sending, requestMessage, requestError, challenge, sendRequest,
} = useTransportRequest();
const mobileMenuOpen = ref(false);
const closeMenu = () => { mobileMenuOpen.value = false; };
const closeMenuOnOutsideClick = (event) => {
  if (mobileMenuOpen.value && !event.target.closest('.site-header__inner')) closeMenu();
};

onMounted(async () => {
  try {
    const response = await fetch('/api/public.php', { cache: 'no-store' });
    if (!response.ok) return;
    const data = await response.json();
    if (typeof data?.challenge === 'string') challenge.value = data.challenge;
    if (data && data.settings) settings.value = {
      ...settings.value,
      ...data.settings,
      primary_phone: formatRuPhone(data.settings.primary_phone || settings.value.primary_phone),
      secondary_phone: formatRuPhone(data.settings.secondary_phone || settings.value.secondary_phone),
      contact_email: normalizeEmail(data.settings.contact_email || settings.value.contact_email),
    };
  } catch {
    // Default contacts remain visible if API is temporarily unreachable.
  }
});


</script>


<template>
  <div class="accessible-taxi" id="top" @click="closeMenuOnOutsideClick">
    <header class="site-header">
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

    <main>
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

      <section class="benefits container" id="benefits" aria-labelledby="benefits-title">
        <h2 id="benefits-title" class="visually-hidden">Почему выбирают нас</h2>
        <div class="benefits__grid">
          <article><span class="benefits__icon"><TransportIcon name="clock" /></span><div><h3>Быстро</h3><p>Обсудим ближайшее доступное время</p></div></article>
          <article><span class="benefits__icon"><TransportIcon name="shield" /></span><div><h3>Надёжно</h3><p>Специализированный транспорт</p></div></article>
          <article><span class="benefits__icon"><TransportIcon name="armchair" /></span><div><h3>Удобно</h3><p>Поездка с учётом потребностей пассажира</p></div></article>
          <article><span class="benefits__icon"><TransportIcon name="heart-hand" /></span><div><h3>С сопровождением</h3><p>Помощь при необходимости</p></div></article>
        </div>
      </section>

      <section class="destinations container" id="destinations" aria-labelledby="destinations-title">
        <div class="section-heading"><div><span class="eyebrow">НАПРАВЛЕНИЯ ПОЕЗДОК</span><h2 id="destinations-title">Транспортировка с сопровождением</h2></div><p>От медицинских учреждений до повседневных дел. Обсудим маршрут и необходимые условия заранее.</p></div>
        <div class="destination-grid">
          <article><span class="destination-icon"><TransportIcon name="hospital" /></span><h3>Из больницы домой</h3><p>После лечения и выписки</p></article>
          <article><span class="destination-icon"><TransportIcon name="stethoscope" /></span><h3>Из дома в поликлинику</h3><p>На приём к врачу</p></article>
          <article><span class="destination-icon"><TransportIcon name="file-check" /></span><h3>Из дома в МФЦ</h3><p>Для оформления документов</p></article>
          <article><span class="destination-icon"><TransportIcon name="activity" /></span><h3>На обследование</h3><p>На диагностику и процедуры</p></article>
          <article><span class="destination-icon"><TransportIcon name="landmark" /></span><h3>В социальные учреждения</h3><p>По важным вопросам</p></article>
          <article><span class="destination-icon"><TransportIcon name="map-pin" /></span><h3>По личным делам</h3><p>На встречи и мероприятия</p></article>
        </div>
      </section>

      <section class="fleet" id="fleet" aria-labelledby="fleet-title">
        <div class="container">
          <div class="section-heading"><div><span class="eyebrow">ФОТОГАЛЕРЕЯ</span><h2 id="fleet-title">Наш автопарк</h2></div><p>Honda N-BOX (G Slope) со складной задней рампой для заезда инвалидной коляски и креплениями для её фиксации.</p></div>
          <div class="fleet-grid">
            <article class="fleet-card">
              <img :src="photoVan" alt="Серая Honda N-BOX G Slope с открытой задней дверью и складной рампой для коляски" width="1200" height="900" loading="lazy" decoding="async" />
              <div class="fleet-card__body"><h3>Специализированный автомобиль</h3><p>Honda N-BOX G Slope с задней рампой</p></div>
            </article>
            <article class="fleet-card">
              <img :src="photoPassenger" alt="Пожилая женщина в инвалидной коляске с темноволосым мужчиной-сопровождающим рядом с серой Honda N-BOX" width="1200" height="900" loading="lazy" decoding="async" />
              <div class="fleet-card__body"><h3>Комфорт пассажира</h3><p>Забота о человеке в течение поездки</p></div>
            </article>
            <article class="fleet-card">
              <img :src="photoBoarding" alt="Мужчина помогает пожилой женщине в коляске заехать по задней рампе серой Honda N-BOX" width="1200" height="900" loading="lazy" decoding="async" />
              <div class="fleet-card__body"><h3>Помощь при посадке</h3><p>Возможность сопровождения по запросу</p></div>
            </article>
          </div>
          <p class="fleet__note">Изображения сгенерированы для демонстрации услуги. Это не фотографии реального автомобиля компании.</p>
        </div>
      </section>

      <section class="order container" id="pricing" aria-labelledby="pricing-title">
        <div class="price-panel">
          <span class="eyebrow">СТОИМОСТЬ ПЕРЕВОЗКИ</span>
          <h2 id="pricing-title">Поездка <span>от 1 500 ₽</span></h2>
          <p>Стоимость зависит от маршрута и дополнительных услуг. Точную цену сообщим после уточнения деталей.</p>
          <a :href="primaryPhoneHref" class="cta cta--white">☎ Уточнить по телефону</a>
        </div>
        <div class="request-panel" id="request">
          <span class="eyebrow">ЗАЯВКА НА ПЕРЕВОЗКУ</span>
          <h2>Расскажите, куда нужно ехать</h2>
          <p>Заполните форму — заявка автоматически поступит к нам на почту. Также можно позвонить или написать в MAX.</p>
          <form class="request-form" @submit.prevent="sendRequest">
            <label for="taxi-from">Откуда забрать</label>
            <input id="taxi-from" v-model.trim="departure" type="text" autocomplete="street-address" placeholder="Адрес отправления" required maxlength="250" />
            <label for="taxi-to">Куда доставить</label>
            <input id="taxi-to" v-model.trim="destination" type="text" placeholder="Адрес назначения" required maxlength="250" />
            <div class="request-form__row">
              <div><label for="taxi-person">Ваше имя</label><input id="taxi-person" v-model.trim="passenger" type="text" autocomplete="name" placeholder="Имя" maxlength="120" /></div>
              <div><label for="taxi-phone">Телефон для связи</label><input id="taxi-phone" v-model.trim="contact" type="tel" autocomplete="tel" inputmode="tel" placeholder="+7 (999) 123-45-67" required maxlength="21" @input="applyPhoneInput($event, value => contact = value)" @focus="phoneFocus($event, value => contact = value)" @blur="phoneBlur($event, value => contact = value)" /></div>
            </div>
            <input v-model="website" class="request-form__honeypot" type="text" tabindex="-1" autocomplete="off" aria-hidden="true" />
            <label class="request-consent"><input v-model="consent" type="checkbox" required /> <span>Согласен на обработку имени, телефона и адресов поездки для ответа на заявку</span></label>
            <button type="submit" :disabled="sending" class="cta cta--red">{{ sending ? 'Отправляем…' : 'Отправить заявку' }}</button>
            <p v-if="requestMessage" :class="requestError ? 'request-error' : 'request-success'" role="status" aria-live="polite">{{ requestMessage }}</p>
            <small id="request-note">Заявка направляется на почту службы перевозок. Если отправка не удалась, позвоните по телефону.</small>
            <a :href="primaryPhoneHref" class="cta cta--outline">Позвонить для заказа</a>
          </form>
        </div>
      </section>
    </main>
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
  </div>
</template>

<style src="./styles/landing.css" scoped></style>
