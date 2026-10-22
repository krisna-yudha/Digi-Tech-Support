<script setup>
import OperationalTemplatesSection from '../components/OperationalTemplatesSection.vue';
</script>

<template>
  <section class="grid">
    <div class="page-title-wrap" style="grid-column: 1 / -1;">
      <h2 class="page-title">Template Operasional</h2>
      <p class="page-desc">
        Kelola dan gunakan template respons, eskalasi tiket, dan dokumen operasional.
      </p>
    </div>

    <OperationalTemplatesSection />
  </section>
</template>

<style scoped>
.page-title-wrap {
  display: flex;
  flex-direction: column;
  gap: 4px;
}

.page-title {
  margin: 0;
  font-size: 1.4rem;
  font-weight: 800;
  color: #0f172a;
}

.page-desc {
  margin: 0;
  color: var(--muted, #64748b);
  font-size: 0.88rem;
}
</style>
