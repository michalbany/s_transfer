<script setup lang="ts">
import { usePage } from "@inertiajs/vue3";

const { props } = usePage();

function HandleCopyLink() {
  navigator.clipboard.writeText(props.link as string);
  alert("Link copied to clipboard");
}
</script>

<template>
  <h1>S Transfer</h1>
  <h2>Package uploaded successfully!</h2>
  <p>You can now view your package:</p>
  <button @click="HandleCopyLink">Copy Link</button>
</template>
