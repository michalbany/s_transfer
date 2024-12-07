<script setup lang="ts">
import { usePage, Link } from "@inertiajs/vue3";

const { props } = usePage();
</script>

<template>
  <h1>S Transfer</h1>
  <h2>Package ready!</h2>
  <p>You can now download your package:</p>
  <!-- Použijeme props.link namísto props.url -->
  <a :href="props.link" class="btn btn-primary">Download Package</a>
</template>
