<script setup lang="ts">
import { usePage, Link } from "@inertiajs/vue3";
import { Button } from "@/Components/ui/button";
import DefaultLayout from "@/Layouts/DefaultLayout.vue";
import { toast } from "vue-sonner";
import { Head, router } from "@inertiajs/vue3";
import { ref } from "vue";

const { props } = usePage();
</script>

<template>
    <DefaultLayout>
        <Head title="Download" />

        <div class="p-6 py-2 mb-2">
            <div class="flex justify-center items-center flex-col">
                <svg class="block size-16 text-primary" xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M20 7h-3a2 2 0 0 1-2-2V2"/><path d="M9 18a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h7l4 4v10a2 2 0 0 1-2 2Z"/><path d="M3 7.6v12.8A1.6 1.6 0 0 0 4.6 22h9.8"/></g></svg>
                <p class="text-sm mt-2">Files ready to download!</p>
            </div>
        </div>

        <h2 class="bg-slate-50 px-4 text-sm text-slate-600 py-1 border-t border-b border-muted">Download below</h2>

        <div class="px-4 mt-4">
            <Button as="a" class="w-full" :href="props.link">
                Download Package
            </Button>
        </div>
    </DefaultLayout>
</template>
