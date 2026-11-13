<script setup>
import SiteHeader from './components/landing/SiteHeader.vue';
import HeroSection from './components/landing/HeroSection.vue';
import BenefitsSection from './components/landing/BenefitsSection.vue';
import DestinationsSection from './components/landing/DestinationsSection.vue';
import FleetSection from './components/landing/FleetSection.vue';
import PricingRequestSection from './components/landing/PricingRequestSection.vue';
import SiteFooter from './components/landing/SiteFooter.vue';
import { useSiteContacts } from './composables/useSiteContacts.js';

const { settings } = useSiteContacts();
</script>

<template>
  <div class="accessible-taxi" id="top">
    <SiteHeader :settings="settings" />
    <main>
      <HeroSection :settings="settings" />
      <BenefitsSection />
      <DestinationsSection />
      <FleetSection />
      <PricingRequestSection :settings="settings" />
    </main>
    <SiteFooter :settings="settings" />
  </div>
</template>

<style src="./styles/landing.css"></style>
