<script setup>
import TransportIcon from '../TransportIcon.vue';
</script>

<template>
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
</template>
