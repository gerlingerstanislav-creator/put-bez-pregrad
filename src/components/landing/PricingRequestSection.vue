<script setup>
import { computed } from 'vue';
import { useTransportRequest } from '../../composables/useTransportRequest.js';
import { applyPhoneInput, phoneFocus, phoneBlur, telHref } from '../../utils/contactInput.js';

const props = defineProps({
  settings: { type: Object, required: true },
});
const primaryPhoneHref = computed(() => telHref(props.settings.primary_phone));
const {
  departure, destination, passenger, contact, website,
  consent, sending, requestMessage, requestError, challenge, sendRequest,
} = useTransportRequest();
</script>

<template>
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
</template>
