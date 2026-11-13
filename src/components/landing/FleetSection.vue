<script setup>
const photoVan = '/images/accessible-van.avif';
const photoPassenger = '/images/accessible-passenger.avif';
const photoBoarding = '/images/accessible-boarding.avif';
</script>

<template>
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
</template>
