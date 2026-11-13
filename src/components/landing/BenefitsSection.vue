<script setup>
import TransportIcon from '../TransportIcon.vue';
</script>

<template>
<section class="benefits container" id="benefits" aria-labelledby="benefits-title">
        <h2 id="benefits-title" class="visually-hidden">Почему выбирают нас</h2>
        <div class="benefits__grid">
          <article><span class="benefits__icon"><TransportIcon name="clock" /></span><div><h3>Быстро</h3><p>Обсудим ближайшее доступное время</p></div></article>
          <article><span class="benefits__icon"><TransportIcon name="shield" /></span><div><h3>Надёжно</h3><p>Специализированный транспорт</p></div></article>
          <article><span class="benefits__icon"><TransportIcon name="armchair" /></span><div><h3>Удобно</h3><p>Поездка с учётом потребностей пассажира</p></div></article>
          <article><span class="benefits__icon"><TransportIcon name="heart-hand" /></span><div><h3>С сопровождением</h3><p>Помощь при необходимости</p></div></article>
        </div>
      </section>
</template>
