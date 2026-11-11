<script setup>
import { onMounted } from 'vue'
import { useRoute } from 'vue-router'
import PublicHeader from './components/PublicHeader.vue'
import PublicFooter from './components/PublicFooter.vue'
import CabinetLayout from './components/CabinetLayout.vue'
import { auth } from './services/auth.js'
import { loadSchool } from './services/school.js'

const route = useRoute()
onMounted(loadSchool)
</script>

<template>
  <a href="#content" class="skip-link">Перейти к содержимому</a>
  <template v-if="route.meta.cabinet">
    <CabinetLayout v-if="auth.user">
      <RouterView :key="route.name === 'admin-section' ? route.params.section : route.name" />
    </CabinetLayout>
  </template>
  <div v-else class="public-layout">
    <PublicHeader />
    <main id="content"><RouterView /></main>
    <PublicFooter />
  </div>
</template>
