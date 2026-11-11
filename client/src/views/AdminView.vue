<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { adminTables } from '../admin/tables.js'
import AdminList from '../components/AdminList.vue'
import AdminEnrollmentsView from './admin/AdminEnrollmentsView.vue'
import AdminExamResultsView from './admin/AdminExamResultsView.vue'
import InstructorReportView from './admin/InstructorReportView.vue'

const route = useRoute()
const section = computed(() => route.params.section)
</script>

<template>
  <AdminEnrollmentsView v-if="section === 'enrollments'" />
  <AdminExamResultsView v-else-if="section === 'exam-results'" />
  <InstructorReportView v-else-if="section === 'instructor-report'" />
  <AdminList v-else :key="section" :config="adminTables[section]" />
</template>
