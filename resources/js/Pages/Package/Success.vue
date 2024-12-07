<script setup lang="ts">
import { usePage, Link } from "@inertiajs/vue3";
import { Button } from "@/Components/ui/button";
import DefaultLayout from "@/Layouts/DefaultLayout.vue";
import { toast } from "vue-sonner";
import { Head, router } from "@inertiajs/vue3";
import { ref } from "vue";

const { props } = usePage();

const input = ref();

const copied = ref(false);

function HandleCopyLinkFromInput() {
    input.value.select();
    navigator.clipboard.writeText(props.link as string);
    toast.success("Link copied to clipboard!");
    copied.value = true;
}
</script>

<template>
    <DefaultLayout>
        <Head title="Success" />

        <div class="p-6 py-2 mb-2">
            <div class="flex justify-center items-center flex-col">
                <svg class="block size-16 text-primary" xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 6L9 17l-5-5"/></svg>
                <p class="text-sm mt-2">Package uploaded successfully!</p>
            </div>
        </div>

        <h2 class="bg-slate-50 px-4 text-sm text-slate-600 py-1 border-t border-b border-muted">Copy the link</h2>

        <div class="mx-4 mt-4 flex items-center gap-2">
            <input ref="input" type="text" class="grow border border-muted rounded-lg p-2" @click="HandleCopyLinkFromInput" :value="props.link" readonly />
            <Button @click="HandleCopyLinkFromInput" size="icon" variant="secondary"><svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><rect width="14" height="14" x="8" y="8" rx="2" ry="2"/><path d="M4 16c-1.1 0-2-.9-2-2V4c0-1.1.9-2 2-2h10c1.1 0 2 .9 2 2"/></g></svg></Button>
        </div>

        <span class="text-xs text-slate-600 px-4 mt-2">Files will be available for 7 days</span>

        <div class="px-4 mt-4">
            <Button class="w-full" :disabled="!copied">
                <Link :href="route('packages.create')" class="w-full h-full">
                Send another
                </Link>
            </Button>
        </div>
    </DefaultLayout>
</template>
